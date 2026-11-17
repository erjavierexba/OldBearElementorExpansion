<?php
namespace OldBearExpansion\ThemeBuilder;

use Elementor\App\Modules\ImportExport\Runners\Import\Import_Runner_Base;
use Elementor\App\Modules\ImportExport\Utils as ImportExportUtils;
use Elementor\Plugin as Elementor;
use Elementor\TemplateLibrary\Source_Local;

if (!defined('ABSPATH')) { exit; }

/** Same name as Elementor's runner ("templates"), so it replaces it. */
final class Kit_Templates_Runner extends Import_Runner_Base {
    private string $session = '';

    public static function get_name(): string { return 'templates'; }

    public function should_import(array $data) {
        return isset($data['include']) && in_array('templates', (array) $data['include'], true)
            && !empty($data['extracted_directory_path']) && !empty($data['manifest']['templates']);
    }

    public function import(array $data, array $imported_data) {
        $this->session = (string) ($data['session_id'] ?? '');
        $path = $data['extracted_directory_path'] . 'templates/';
        $result = ['templates'=>['succeed'=>[],'failed'=>[]]];
        foreach ($data['manifest']['templates'] as $id => $settings) {
            try {
                $result['templates']['succeed'][$id] = $this->import_template((string) $id, (array) $settings, $path);
            } catch (\Throwable $error) {
                $result['templates']['failed'][$id] = $error->getMessage();
            }
        }
        Locations::flush();
        return $result;
    }

    private function import_template(string $id, array $settings, string $path): int {
        $type = (string) ($settings['doc_type'] ?? '');
        if (!Elementor::$instance->documents->get_document_type($type, false)) {
            throw new \Exception(sprintf('Tipo de plantilla no compatible todavía: %s', $type));
        }
        $data = ImportExportUtils::read_json_file($path . basename($id));
        if (!is_array($data)) { throw new \Exception('No se puede leer la plantilla.'); }
        $document = Elementor::$instance->documents->create($type, ['post_title'=>(string) ($settings['title'] ?? $id),'post_type'=>Source_Local::CPT,'post_status'=>'publish']);
        if (is_wp_error($document)) { throw new \Exception($document->get_error_message()); }
        $data['import_settings'] = $settings;
        $data['id'] = $id;
        $attachment = function($attachment_id) { $this->set_session_post_meta($attachment_id, $this->session); };
        add_filter('elementor/template_library/import_images/new_attachment', $attachment);
        $document->import($data);
        remove_filter('elementor/template_library/import_images/new_attachment', $attachment);
        $post_id = (int) $document->get_main_id();
        $this->set_session_post_meta($post_id, $this->session);
        if ($document instanceof Location_Document) {
            Location_Document::store_conditions($post_id, Kit_Import::manifest_conditions($settings));
        }
        return $post_id;
    }
}
