<?php
namespace OldBearExpansion\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Icons_Manager;
use Elementor\Repeater;
use OldBearExpansion\Forms\Handler;
use OldBearExpansion\Forms\Integrations;

if (!defined('ABSPATH')) { exit; }

/**
 * Form with Elementor Pro's setting keys, so forms built with Pro keep working.
 * Submissions go to our own REST endpoint (see includes/forms/class-handler.php),
 * which re-reads the widget settings from the saved document, never from the request.
 */
final class Form extends Element {
    public const PRO = 'form';
    public const TYPES = ['text','email','textarea','url','tel','radio','select','checkbox','acceptance','number','date','time','upload','password','html','hidden','step'];
    public const WIDTHS = ['' => 'Por defecto','100'=>'100%','80'=>'80%','75'=>'75%','70'=>'70%','66'=>'66%','60'=>'60%','50'=>'50%','40'=>'40%','33'=>'33%','30'=>'30%','25'=>'25%','20'=>'20%'];
    public const ACTIONS = ['save-to-database'=>'Guardar envíos','email'=>'Correo','email2'=>'Segundo correo','redirect'=>'Redirigir','webhook'=>'Webhook','mailchimp'=>'Mailchimp','drip'=>'Drip','activecampaign'=>'ActiveCampaign','getresponse'=>'GetResponse','convertkit'=>'Kit (ConvertKit)','mailerlite'=>'MailerLite','slack'=>'Slack','discord'=>'Discord','popup'=>'Popup'];
    public function get_title(): string { return 'Formulario'; }
    public function get_icon(): string { return 'eicon-form-horizontal'; }
    public function get_keywords(): array { return ['form','formulario','contacto','contact','email','correo','campos']; }
    public function get_script_depends(): array { return ['obx-widgets']; }

    private static function type_labels(): array {
        return ['text'=>'Texto','email'=>'Correo electrónico','textarea'=>'Área de texto','url'=>'Dirección web','tel'=>'Teléfono','radio'=>'Opción única','select'=>'Desplegable','checkbox'=>'Casillas','acceptance'=>'Aceptación','number'=>'Número','date'=>'Fecha','time'=>'Hora','upload'=>'Subir archivo','password'=>'Contraseña','html'=>'HTML','hidden'=>'Oculto','step'=>'Paso'];
    }

    protected function register_controls(): void {
        $this->start_controls_section('section_form_fields', ['label'=>'Campos del formulario']);
        $this->add_control('form_name', ['label'=>'Nombre del formulario','type'=>Controls_Manager::TEXT,'default'=>'Nuevo formulario']);

        $r = new Repeater();
        $r->start_controls_tabs('form_fields_tabs');
        $r->start_controls_tab('form_fields_content_tab', ['label'=>'Contenido']);
        $r->add_control('field_type', ['label'=>'Tipo','type'=>Controls_Manager::SELECT,'default'=>'text','options'=>self::type_labels()]);
        $r->add_control('field_label', ['label'=>'Etiqueta','type'=>Controls_Manager::TEXT,'default'=>'','condition'=>['field_type!'=>'html']]);
        $r->add_control('placeholder', ['label'=>'Texto de ejemplo','type'=>Controls_Manager::TEXT,'default'=>'','condition'=>['field_type'=>['tel','text','email','textarea','number','url','password']]]);
        $r->add_control('required', ['label'=>'Obligatorio','type'=>Controls_Manager::SWITCHER,'return_value'=>'true','default'=>'','condition'=>['field_type!'=>['checkbox','recaptcha','recaptcha_v3','hidden','html','step']]]);
        $r->add_control('field_options', ['label'=>'Opciones','type'=>Controls_Manager::TEXTAREA,'default'=>'','description'=>'Una opción por línea. Para separar etiqueta y valor usa «|», por ejemplo: Primera opción|primera.','condition'=>['field_type'=>['select','checkbox','radio']]]);
        $r->add_control('allow_multiple', ['label'=>'Selección múltiple','type'=>Controls_Manager::SWITCHER,'return_value'=>'true','condition'=>['field_type'=>'select']]);
        $r->add_control('select_size', ['label'=>'Filas visibles','type'=>Controls_Manager::NUMBER,'min'=>2,'step'=>1,'condition'=>['field_type'=>'select','allow_multiple'=>'true']]);
        $r->add_control('inline_list', ['label'=>'En línea','type'=>Controls_Manager::SWITCHER,'return_value'=>'elementor-subgroup-inline','condition'=>['field_type'=>['checkbox','radio']]]);
        $r->add_control('field_html', ['label'=>'HTML','type'=>Controls_Manager::TEXTAREA,'dynamic'=>['active'=>true],'condition'=>['field_type'=>'html']]);
        $r->add_responsive_control('width', ['label'=>'Ancho de la columna','type'=>Controls_Manager::SELECT,'options'=>self::WIDTHS,'default'=>'100','condition'=>['field_type!'=>['hidden','recaptcha','recaptcha_v3','step']]]);
        $r->add_control('rows', ['label'=>'Filas','type'=>Controls_Manager::NUMBER,'default'=>4,'condition'=>['field_type'=>'textarea']]);
        $r->add_control('recaptcha_size', ['type'=>Controls_Manager::HIDDEN,'default'=>'normal']);
        $r->add_control('css_classes', ['type'=>Controls_Manager::HIDDEN,'default'=>'']);
        $r->add_control('field_min', ['label'=>'Mínimo','type'=>Controls_Manager::NUMBER,'condition'=>['field_type'=>'number']]);
        $r->add_control('field_max', ['label'=>'Máximo','type'=>Controls_Manager::NUMBER,'condition'=>['field_type'=>'number']]);
        $r->add_control('acceptance_text', ['label'=>'Texto de aceptación','type'=>Controls_Manager::TEXTAREA,'condition'=>['field_type'=>'acceptance']]);
        $r->add_control('checked_by_default', ['label'=>'Marcada por defecto','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','condition'=>['field_type'=>'acceptance']]);
        $r->add_control('min_date', ['label'=>'Fecha mínima','type'=>Controls_Manager::DATE_TIME,'picker_options'=>['enableTime'=>false],'condition'=>['field_type'=>'date']]);
        $r->add_control('max_date', ['label'=>'Fecha máxima','type'=>Controls_Manager::DATE_TIME,'picker_options'=>['enableTime'=>false],'condition'=>['field_type'=>'date']]);
        $r->add_control('use_native_date', ['label'=>'Selector nativo','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','condition'=>['field_type'=>'date']]);
        $r->add_control('use_native_time', ['label'=>'Selector nativo','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','condition'=>['field_type'=>'time']]);
        $r->add_control('file_sizes', ['label'=>'Tamaño máximo (MB)','type'=>Controls_Manager::SELECT,'options'=>[''=>'Por defecto'] + array_combine(array_map('strval', range(1, 100)), array_map(static fn($n) => $n . ' MB', range(1, 100))),'condition'=>['field_type'=>'upload']]);
        $r->add_control('file_types', ['label'=>'Tipos permitidos','type'=>Controls_Manager::TEXT,'description'=>'Extensiones separadas por comas, por ejemplo: pdf,jpg,png.','condition'=>['field_type'=>'upload']]);
        $r->add_control('allow_multiple_upload', ['label'=>'Varios archivos','type'=>Controls_Manager::SWITCHER,'return_value'=>'yes','condition'=>['field_type'=>'upload']]);
        $r->add_control('max_files', ['label'=>'Máximo de archivos','type'=>Controls_Manager::NUMBER,'condition'=>['field_type'=>'upload','allow_multiple_upload'=>'yes']]);
        $r->add_control('previous_button', ['label'=>'Botón anterior','type'=>Controls_Manager::TEXT,'condition'=>['field_type'=>'step']]);
        $r->add_control('next_button', ['label'=>'Botón siguiente','type'=>Controls_Manager::TEXT,'condition'=>['field_type'=>'step']]);
        $r->add_control('selected_icon', ['label'=>'Icono','type'=>Controls_Manager::ICONS,'condition'=>['field_type'=>'step']]);
        $r->end_controls_tab();
        $r->start_controls_tab('form_fields_advanced_tab', ['label'=>'Avanzado','condition'=>['field_type!'=>'html']]);
        $r->add_control('field_value', ['label'=>'Valor por defecto','type'=>Controls_Manager::TEXT,'dynamic'=>['active'=>true],'condition'=>['field_type'=>['text','email','textarea','url','tel','radio','select','number','date','time','hidden']]]);
        $r->add_control('custom_id', ['label'=>'ID','type'=>Controls_Manager::TEXT,'description'=>'Identificador único del campo (letras, números y guiones bajos). Se usa en [field id="…"].','render_type'=>'none','ai'=>['active'=>false]]);
        $r->end_controls_tab();
        $r->end_controls_tabs();

        $this->add_control('form_fields', ['type'=>Controls_Manager::REPEATER,'fields'=>$r->get_controls(),'default'=>[
            ['custom_id'=>'name','field_type'=>'text','field_label'=>'Nombre','placeholder'=>'Nombre','width'=>'100'],
            ['custom_id'=>'email','field_type'=>'email','required'=>'true','field_label'=>'Correo electrónico','placeholder'=>'Correo electrónico','width'=>'100'],
            ['custom_id'=>'message','field_type'=>'textarea','field_label'=>'Mensaje','placeholder'=>'Mensaje','width'=>'100'],
        ],'title_field'=>'{{{ field_label || field_type }}}']);
        $this->add_control('input_size', ['label'=>'Tamaño de los campos','type'=>Controls_Manager::SELECT,'default'=>'sm','options'=>self::sizes(),'separator'=>'before']);
        $this->add_control('show_labels', ['label'=>'Etiquetas','type'=>Controls_Manager::SWITCHER,'default'=>'yes']);
        $this->add_control('mark_required', ['label'=>'Marcar obligatorios','type'=>Controls_Manager::SWITCHER,'default'=>'','condition'=>['show_labels!'=>'']]);
        $this->add_control('label_position', ['type'=>Controls_Manager::HIDDEN,'default'=>'above']);
        $this->end_controls_section();

        $this->start_controls_section('section_buttons', ['label'=>'Botones']);
        $this->add_control('button_size', ['label'=>'Tamaño','type'=>Controls_Manager::SELECT,'default'=>'sm','options'=>self::sizes()]);
        $this->add_responsive_control('button_width', ['label'=>'Ancho de la columna','type'=>Controls_Manager::SELECT,'options'=>self::WIDTHS,'default'=>'100']);
        $this->add_responsive_control('button_align', ['label'=>'Alineación','type'=>Controls_Manager::CHOOSE,'default'=>'stretch','options'=>self::logical_align() + ['stretch'=>['title'=>'Ancho completo','icon'=>'eicon-h-align-stretch']],'prefix_class'=>'obx-form%s-button-align-']);
        $this->add_control('step_next_label', ['label'=>'Texto «Siguiente»','type'=>Controls_Manager::TEXT,'default'=>'Siguiente','separator'=>'before']);
        $this->add_control('step_previous_label', ['label'=>'Texto «Anterior»','type'=>Controls_Manager::TEXT,'default'=>'Anterior']);
        $this->add_control('button_text', ['label'=>'Texto del botón de envío','type'=>Controls_Manager::TEXT,'default'=>'Enviar','separator'=>'before']);
        $this->add_control('selected_button_icon', ['label'=>'Icono','type'=>Controls_Manager::ICONS,'skin'=>'inline','label_block'=>false]);
        $this->add_control('button_icon_align', ['label'=>'Posición del icono','type'=>Controls_Manager::CHOOSE,'default'=>is_rtl() ? 'row-reverse' : 'row','options'=>['row'=>['title'=>'Inicio','icon'=>'eicon-h-align-left'],'row-reverse'=>['title'=>'Final','icon'=>'eicon-h-align-right'],'left'=>['title'=>'Izquierda','icon'=>'eicon-h-align-left'],'right'=>['title'=>'Derecha','icon'=>'eicon-h-align-right']],'condition'=>['selected_button_icon[value]!'=>'']]);
        $this->slider('button_icon_indent', 'Separación del icono', '.obx-form__submit .elementor-button-content-wrapper', 'gap: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>50]],'condition'=>['selected_button_icon[value]!'=>'']]);
        $this->add_control('button_css_id', ['label'=>'ID del botón','type'=>Controls_Manager::TEXT,'default'=>'','ai'=>['active'=>false]]);
        $this->end_controls_section();

        $this->start_controls_section('section_integration', ['label'=>'Acciones tras el envío']);
        $this->add_control('submit_actions', ['label'=>'Acciones','type'=>Controls_Manager::SELECT2,'multiple'=>true,'options'=>self::ACTIONS,'default'=>['email'],'label_block'=>true,'render_type'=>'none']);
        $this->end_controls_section();

        foreach (['' => 'Correo', '_2' => 'Segundo correo'] as $suffix => $label) {
            $action = $suffix === '' ? 'email' : 'email2';
            $this->start_controls_section('section_email' . $suffix, ['label'=>$label,'condition'=>['submit_actions'=>$action]]);
            $this->add_control('email_to' . $suffix, ['label'=>'Para','type'=>Controls_Manager::TEXT,'default'=>$suffix === '' ? (string) get_option('admin_email') : '','placeholder'=>(string) get_option('admin_email'),'description'=>'Separa varias direcciones con comas.','label_block'=>true,'render_type'=>'none']);
            $this->add_control('email_subject' . $suffix, ['label'=>'Asunto','type'=>Controls_Manager::TEXT,'default'=>sprintf('Nuevo mensaje desde «%s»', get_option('blogname')),'label_block'=>true,'render_type'=>'none']);
            $this->add_control('email_content' . $suffix, ['label'=>'Mensaje','type'=>Controls_Manager::TEXTAREA,'default'=>'[all-fields]','description'=>'Usa [all-fields] para incluir todos los campos o [field id="…"] para uno concreto.','render_type'=>'none']);
            $domain = (string) wp_parse_url(home_url(), PHP_URL_HOST);
            $this->add_control('email_from' . $suffix, ['label'=>'Remitente','type'=>Controls_Manager::TEXT,'default'=>'email@' . preg_replace('/^www\./', '', $domain),'render_type'=>'none']);
            $this->add_control('email_from_name' . $suffix, ['label'=>'Nombre del remitente','type'=>Controls_Manager::TEXT,'default'=>(string) get_bloginfo('name'),'render_type'=>'none']);
            $this->add_control('email_reply_to' . $suffix, ['label'=>'Responder a','type'=>Controls_Manager::TEXT,'default'=>'','description'=>'Una dirección o [field id="email"].','render_type'=>'none']);
            $this->add_control('email_to_cc' . $suffix, ['label'=>'Cc','type'=>Controls_Manager::TEXT,'default'=>'','render_type'=>'none']);
            $this->add_control('email_to_bcc' . $suffix, ['label'=>'Cco','type'=>Controls_Manager::TEXT,'default'=>'','render_type'=>'none']);
            $this->add_control('form_metadata' . $suffix, ['label'=>'Metadatos','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'separator'=>'before','default'=>$suffix === '' ? ['date','time','page_url','user_agent','remote_ip','credit'] : [],'options'=>['date'=>'Fecha','time'=>'Hora','page_url'=>'Página','user_agent'=>'Navegador','remote_ip'=>'IP','credit'=>'Firma'],'render_type'=>'none']);
            $this->add_control('email_content_type' . $suffix, ['label'=>'Formato','type'=>Controls_Manager::SELECT,'default'=>'html','options'=>['html'=>'HTML','plain'=>'Texto sin formato'],'render_type'=>'none']);
            $this->end_controls_section();
        }

        $this->start_controls_section('section_redirect', ['label'=>'Redirigir','condition'=>['submit_actions'=>'redirect']]);
        $this->add_control('redirect_to', ['label'=>'Redirigir a','type'=>Controls_Manager::TEXT,'placeholder'=>'https://…','label_block'=>true,'render_type'=>'none','dynamic'=>['active'=>true]]);
        $this->end_controls_section();

        $this->start_controls_section('section_webhook', ['label'=>'Webhook','condition'=>['submit_actions'=>'webhook']]);
        $this->add_control('webhooks', ['label'=>'Dirección del webhook','type'=>Controls_Manager::TEXT,'placeholder'=>'https://…','label_block'=>true,'render_type'=>'none','description'=>'Se envían los datos del formulario por POST a esta dirección.']);
        $this->add_control('webhooks_advanced_data', ['label'=>'Datos avanzados','type'=>Controls_Manager::SWITCHER,'default'=>'no','render_type'=>'none']);
        $this->end_controls_section();

        $this->start_controls_section('section_submissions', ['label'=>'Envíos guardados','condition'=>['submit_actions'=>'save-to-database']]);
        $this->add_control('submissions_metadata', ['label'=>'Guardar también','type'=>Controls_Manager::SELECT2,'multiple'=>true,'label_block'=>true,'default'=>['remote_ip','user_agent'],'options'=>['remote_ip'=>'IP','user_agent'=>'Navegador'],'render_type'=>'none']);
        $this->end_controls_section();

        Integrations::controls($this);

        $this->start_controls_section('section_form_options', ['label'=>'Opciones adicionales']);
        $this->add_control('form_id', ['label'=>'ID del formulario','type'=>Controls_Manager::TEXT,'default'=>'','description'=>'Identificador del formulario en la página (sin espacios).','ai'=>['active'=>false]]);
        $this->add_control('form_validation', ['label'=>'Validación del formulario','type'=>Controls_Manager::SELECT,'default'=>'','options'=>[''=>'Navegador','custom'=>'Mensajes propios']]);
        $this->add_control('custom_messages', ['label'=>'Mensajes personalizados','type'=>Controls_Manager::SWITCHER,'default'=>'','separator'=>'before','render_type'=>'none']);
        foreach (self::messages() as $key => $text) {
            $this->add_control($key, ['label'=>['success_message'=>'Mensaje de éxito','error_message'=>'Mensaje de error','server_message'=>'Error del servidor','invalid_message'=>'Formulario no válido','required_field_message'=>'Campo obligatorio'][$key],'type'=>Controls_Manager::TEXT,'default'=>$text,'label_block'=>true,'condition'=>['custom_messages!'=>''],'render_type'=>'none']);
        }
        $this->end_controls_section();

        $this->start_controls_section('section_steps_settings', ['label'=>'Pasos']);
        $this->add_control('step_type', ['label'=>'Indicador','type'=>Controls_Manager::SELECT,'default'=>'number_text','options'=>['none'=>'Ninguno','text'=>'Texto','icon'=>'Icono','number'=>'Número','progress_bar'=>'Barra de progreso','number_text'=>'Número y texto','icon_text'=>'Icono y texto']]);
        $this->add_control('step_icon_shape', ['label'=>'Forma','type'=>Controls_Manager::SELECT,'default'=>'circle','options'=>['circle'=>'Círculo','square'=>'Cuadrado','rounded'=>'Redondeado','none'=>'Ninguna'],'condition'=>['step_type!'=>['progress_bar','text','none']]]);
        $this->end_controls_section();

        $this->style_controls();
    }

    /** @return array<string,string> */
    public static function messages(): array {
        return [
            'success_message'=>'El formulario se ha enviado correctamente.',
            'error_message'=>'Se ha producido un error.',
            'server_message'=>'No se ha podido enviar por un error del servidor.',
            'invalid_message'=>'Hay algún problema: el formulario no es válido.',
            'required_field_message'=>'Este campo es obligatorio.',
        ];
    }

    private function style_controls(): void {
        $this->start_controls_section('section_form_style', ['label'=>'Formulario','tab'=>Controls_Manager::TAB_STYLE]);
        $this->slider('column_gap', 'Separación entre columnas', '.obx-form', '--obx-form-column-gap: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'default'=>['size'=>10],'range'=>['px'=>['max'=>60]]]);
        $this->slider('row_gap', 'Separación entre filas', '.obx-form', '--obx-form-row-gap: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'default'=>['size'=>10],'range'=>['px'=>['max'=>60]]]);
        $this->add_control('heading_label', ['label'=>'Etiquetas','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->slider('label_spacing', 'Separación', '.obx-form__label', 'margin-bottom: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'default'=>['size'=>0],'range'=>['px'=>['max'=>60]]]);
        $this->color('label_color', 'Color', '.obx-form__label, .obx-form__option label');
        $this->color('mark_required_color', 'Color de la marca', '.obx-form__field.is-required .obx-form__label::after', 'color', ['condition'=>['mark_required'=>'yes']]);
        $this->typography('label_typography', '.obx-form__label, .obx-form__option label');
        $this->add_control('heading_html', ['label'=>'Campos HTML','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->slider('html_spacing', 'Separación', '.obx-form__field--html', 'padding-bottom: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'default'=>['size'=>0],'range'=>['px'=>['max'=>60]]]);
        $this->color('html_color', 'Color', '.obx-form__field--html');
        $this->typography('html_typography', '.obx-form__field--html');
        $this->end_controls_section();

        $this->start_controls_section('section_field_style', ['label'=>'Campos','tab'=>Controls_Manager::TAB_STYLE]);
        $this->color('field_text_color', 'Color del texto', '.obx-form__input');
        $this->typography('field_typography', '.obx-form__input');
        $this->color('field_background_color', 'Fondo', '.obx-form__input:not(.obx-form__group)', 'background-color', ['default'=>'#ffffff','separator'=>'before']);
        $this->color('field_border_color', 'Color del borde', '.obx-form__input:not(.obx-form__group)', 'border-color');
        $this->dimensions('field_border_width', 'Grosor del borde', '.obx-form__input:not(.obx-form__group)', 'border-width', ['placeholder'=>'1','size_units'=>['px','em','rem']], false);
        $this->dimensions('field_border_radius', 'Radio del borde', '.obx-form__input:not(.obx-form__group)', 'border-radius', [], false);
        $this->end_controls_section();

        $this->start_controls_section('section_button_style', ['label'=>'Botones','tab'=>Controls_Manager::TAB_STYLE]);
        $this->typography('button_typography', '.obx-form .elementor-button');
        $this->add_responsive_control('button_content_align', ['label'=>'Alineación del contenido','type'=>Controls_Manager::CHOOSE,'condition'=>['button_align'=>'stretch'],
            'options'=>self::logical_align() + ['space-between'=>['title'=>'Separado','icon'=>'eicon-justify-space-between-h']],'selectors_dictionary'=>['start'=>'flex-start','end'=>'flex-end'],
            'selectors'=>['{{WRAPPER}} .obx-form .elementor-button .elementor-button-content-wrapper'=>'display: flex; width: 100%; justify-content: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Border::get_type(), ['name'=>'button_border','selector'=>'{{WRAPPER}} .obx-form .elementor-button','exclude'=>['color']]);
        $this->start_controls_tabs('tabs_button_style');
        $this->start_controls_tab('tab_button_normal', ['label'=>'Normal']);
        $this->add_control('heading_next_submit_button', ['label'=>'Siguiente y enviar','type'=>Controls_Manager::HEADING]);
        $this->color('button_background_color', 'Fondo', '.obx-form__submit, .obx-form__next', 'background-color');
        $this->color('button_text_color', 'Color del texto', '.obx-form__submit, .obx-form__next', 'color', ['default'=>'#ffffff']);
        $this->color('button_border_color', 'Color del borde', '.obx-form__submit, .obx-form__next', 'border-color', ['condition'=>['button_border_border!'=>'']]);
        $this->add_control('heading_previous_button', ['label'=>'Anterior','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->color('previous_button_background_color', 'Fondo', '.obx-form__previous', 'background-color');
        $this->color('previous_button_text_color', 'Color del texto', '.obx-form__previous', 'color', ['default'=>'#ffffff']);
        $this->color('previous_button_border_color', 'Color del borde', '.obx-form__previous', 'border-color', ['condition'=>['button_border_border!'=>'']]);
        $this->end_controls_tab();
        $this->start_controls_tab('tab_button_hover', ['label'=>'Al pasar el ratón']);
        $this->add_control('heading_next_submit_button_hover', ['label'=>'Siguiente y enviar','type'=>Controls_Manager::HEADING]);
        $this->color('button_background_hover_color', 'Fondo', '.obx-form__submit:hover, .obx-form__next:hover', 'background-color');
        $this->color('button_hover_color', 'Color del texto', '.obx-form__submit:hover, .obx-form__next:hover', 'color', ['default'=>'#ffffff']);
        $this->color('button_hover_border_color', 'Color del borde', '.obx-form__submit:hover, .obx-form__next:hover', 'border-color', ['condition'=>['button_border_border!'=>'']]);
        $this->add_control('heading_previous_button_hover', ['label'=>'Anterior','type'=>Controls_Manager::HEADING,'separator'=>'before']);
        $this->color('previous_button_background_color_hover', 'Fondo', '.obx-form__previous:hover', 'background-color');
        $this->color('previous_button_text_color_hover', 'Color del texto', '.obx-form__previous:hover', 'color', ['default'=>'#ffffff']);
        $this->color('previous_button_border_color_hover', 'Color del borde', '.obx-form__previous:hover', 'border-color', ['condition'=>['button_border_border!'=>'']]);
        $this->add_control('hover_transition_duration', ['label'=>'Duración de la transición','type'=>Controls_Manager::SLIDER,'size_units'=>['ms','s'],'default'=>['unit'=>'ms'],'range'=>['ms'=>['min'=>0,'max'=>3000,'step'=>50],'s'=>['min'=>0,'max'=>3,'step'=>0.1]],
            'selectors'=>['{{WRAPPER}} .obx-form .elementor-button'=>'transition-duration: {{SIZE}}{{UNIT}};']]);
        $this->add_control('button_hover_animation', ['label'=>'Animación','type'=>Controls_Manager::HOVER_ANIMATION]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->dimensions('button_border_radius', 'Radio del borde', '.obx-form .elementor-button', 'border-radius', ['separator'=>'before'], false);
        $this->dimensions('button_text_padding', 'Relleno', '.obx-form .elementor-button', 'padding');
        $this->end_controls_section();

        $this->start_controls_section('section_messages_style', ['label'=>'Mensajes','tab'=>Controls_Manager::TAB_STYLE]);
        $this->typography('message_typography', '.obx-form__message, .obx-form__error');
        $this->color('success_message_color', 'Color del mensaje de éxito', '.obx-form__message.is-success');
        $this->color('error_message_color', 'Color del mensaje de error', '.obx-form__message.is-error');
        $this->color('inline_message_color', 'Color de los mensajes en línea', '.obx-form__error');
        $this->end_controls_section();

        $this->start_controls_section('section_steps_style', ['label'=>'Pasos','tab'=>Controls_Manager::TAB_STYLE,'condition'=>['step_type!'=>'none']]);
        $this->typography('steps_typography', '.obx-form__indicator');
        $this->slider('steps_gap', 'Separación', '.obx-form__indicators', 'gap: {{SIZE}}{{UNIT}}; --obx-steps-gap: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>100]]]);
        $this->slider('steps_icon_size', 'Tamaño del indicador', '.obx-form__indicators', '--obx-step-size: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>100]]]);
        $this->slider('steps_padding', 'Relleno', '.obx-form__indicators', '--obx-step-padding: {{SIZE}}{{UNIT}};', ['size_units'=>['px','em','rem'],'range'=>['px'=>['max'=>50]]]);
        $this->start_controls_tabs('steps_state', ['condition'=>['step_type!'=>'progress_bar']]);
        foreach (['inactive'=>'Inactivo','active'=>'Activo','completed'=>'Completado'] as $state => $label) {
            $this->start_controls_tab('tab_steps_' . $state, ['label'=>$label]);
            $this->color('step_' . $state . '_primary_color', 'Color principal', '.obx-form__indicators', '--obx-step-' . $state, ['condition'=>['step_type!'=>'progress_bar']]);
            $this->color('step_' . $state . '_secondary_color', 'Color secundario', '.obx-form__indicators', '--obx-step-' . $state . '-2', ['default'=>'#ffffff','condition'=>['step_type!'=>'progress_bar'] + ($state === 'completed' ? ['step_icon_shape!'=>'none'] : [])]);
            $this->end_controls_tab();
        }
        $this->end_controls_tabs();
        $this->slider('step_divider_width', 'Grosor del separador', '.obx-form__indicators', '--obx-step-divider-width: {{SIZE}}{{UNIT}};', ['size_units'=>['px'],'range'=>['px'=>['max'=>10]],'separator'=>'before','condition'=>['step_type!'=>'progress_bar']]);
        $this->slider('step_divider_gap', 'Hueco del separador', '.obx-form__indicators', '--obx-step-divider-gap: {{SIZE}}{{UNIT}};', ['size_units'=>['px'],'range'=>['px'=>['max'=>50]],'condition'=>['step_type!'=>'progress_bar']]);
        $this->color('step_progress_bar_color', 'Color de la barra', '.obx-form__progress-fill', 'background-color', ['condition'=>['step_type'=>'progress_bar']]);
        $this->color('step_progress_bar_background_color', 'Fondo de la barra', '.obx-form__progress', 'background-color', ['condition'=>['step_type'=>'progress_bar']]);
        $this->slider('step_progress_bar_height', 'Alto de la barra', '.obx-form__progress', 'height: {{SIZE}}{{UNIT}};', ['size_units'=>['px'],'condition'=>['step_type'=>'progress_bar']]);
        $this->slider('step_progress_bar_border_radius', 'Radio de la barra', '.obx-form__progress', 'border-radius: {{SIZE}}{{UNIT}};', ['size_units'=>['px','%'],'condition'=>['step_type'=>'progress_bar']]);
        $this->color('step_progress_bar_percentage_color', 'Color del porcentaje', '.obx-form__progress-fill', 'color', ['condition'=>['step_type'=>'progress_bar']]);
        $this->typography('step_progress_bar_percentage__typography', '.obx-form__progress-fill', ['condition'=>['step_type'=>'progress_bar']]);
        $this->end_controls_section();
    }

    /** Field id as used in posted data and shortcodes. */
    public static function field_id(array $field): string {
        $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($field['custom_id'] ?? ''));
        return $id !== '' ? $id : 'field_' . preg_replace('/[^A-Za-z0-9]/', '', (string) ($field['_id'] ?? ''));
    }

    /** @return array<int,array{0:string,1:string}> [label, value] pairs. */
    public static function options(array $field): array {
        $out = [];
        foreach (preg_split('/\R/', (string) ($field['field_options'] ?? '')) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') { continue; }
            [$label, $value] = array_pad(array_map('trim', explode('|', $line, 2)), 2, null);
            $out[] = [$label, $value ?? $label];
        }
        return $out;
    }

    private static function col_classes(array $field, string $prefix = 'width'): string {
        $classes = [];
        foreach (['' => 'obx-col-', '_tablet' => 'obx-col-md-', '_mobile' => 'obx-col-sm-'] as $device => $class) {
            $width = (string) ($field[$prefix . $device] ?? '');
            if ($width === '' && $device === '') { $width = '100'; }
            if ($width !== '' && isset(self::WIDTHS[$width])) { $classes[] = $class . $width; }
        }
        return implode(' ', $classes);
    }

    private function input(array $field, string $id, string $dom, array $s): string {
        $type = (string) ($field['field_type'] ?? 'text');
        $name = 'form_fields[' . $id . ']';
        $required = ($field['required'] ?? '') === 'true';
        $req = $required ? ' required aria-required="true"' : '';
        $size = ' obx-form__input--' . sanitize_html_class((string) ($s['input_size'] ?? 'sm'));
        $value = (string) ($field['field_value'] ?? '');
        $placeholder = (string) ($field['placeholder'] ?? '') !== '' ? ' placeholder="' . esc_attr($field['placeholder']) . '"' : '';
        switch ($type) {
            case 'textarea':
                return '<textarea class="obx-form__input' . $size . '" id="' . esc_attr($dom) . '" name="' . esc_attr($name) . '" rows="' . max(1, (int) ($field['rows'] ?? 4)) . '"' . $placeholder . $req . '>' . esc_textarea($value) . '</textarea>';
            case 'select':
                $multiple = ($field['allow_multiple'] ?? '') === 'true';
                $html = '<div class="obx-form__select-wrap"><select class="obx-form__input' . $size . '" id="' . esc_attr($dom) . '" name="' . esc_attr($name) . ($multiple ? '[]" multiple' . (!empty($field['select_size']) ? ' size="' . (int) $field['select_size'] . '"' : '') : '"') . $req . '>';
                foreach (self::options($field) as [$label, $option]) { $html .= '<option value="' . esc_attr($option) . '"' . selected($value, $option, false) . '>' . esc_html($label) . '</option>'; }
                return $html . '</select></div>';
            case 'radio':
            case 'checkbox':
                $inline = ($field['inline_list'] ?? '') !== '' ? ' obx-form__group--inline' : '';
                $html = '<div class="obx-form__input obx-form__group' . $inline . '" role="group"' . ($required ? ' data-required="1"' : '') . '>';
                foreach (self::options($field) as $i => [$label, $option]) {
                    $oid = $dom . '-' . $i;
                    $html .= '<span class="obx-form__option"><input type="' . $type . '" id="' . esc_attr($oid) . '" name="' . esc_attr($name . ($type === 'checkbox' ? '[]' : '')) . '" value="' . esc_attr($option) . '"' . checked($value, $option, false) . ($type === 'radio' && $required ? ' required' : '') . '> <label for="' . esc_attr($oid) . '">' . esc_html($label) . '</label></span>';
                }
                return $html . '</div>';
            case 'acceptance':
                $checked = ($field['checked_by_default'] ?? '') === 'yes' ? ' checked' : '';
                return '<div class="obx-form__input obx-form__group"><span class="obx-form__option"><input type="checkbox" id="' . esc_attr($dom) . '" name="' . esc_attr($name) . '" value="on"' . $checked . $req . '> <label for="' . esc_attr($dom) . '">' . wp_kses_post((string) ($field['acceptance_text'] ?? '')) . '</label></span></div>';
            case 'upload':
                $multiple = ($field['allow_multiple_upload'] ?? '') === 'yes';
                $types = array_filter(array_map(static fn($t) => '.' . trim(strtolower($t), " .\t"), explode(',', (string) ($field['file_types'] ?? ''))), static fn($t) => $t !== '.');
                return '<input type="file" class="obx-form__input' . $size . '" id="' . esc_attr($dom) . '" name="' . esc_attr($name) . ($multiple ? '[]" multiple' : '"') . ($types ? ' accept="' . esc_attr(implode(',', $types)) . '"' : '') . (!empty($field['file_sizes']) ? ' data-max-size="' . (int) $field['file_sizes'] . '"' : '') . ($multiple && !empty($field['max_files']) ? ' data-max-files="' . (int) $field['max_files'] . '"' : '') . $req . '>';
            case 'html':
                return '';
            case 'hidden':
                return '<input type="hidden" id="' . esc_attr($dom) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
            default:
                $html_type = in_array($type, ['text','email','url','tel','number','date','time','password'], true) ? $type : 'text';
                $extra = '';
                if ($type === 'number') {
                    if (($field['field_min'] ?? '') !== '') { $extra .= ' min="' . esc_attr($field['field_min']) . '"'; }
                    if (($field['field_max'] ?? '') !== '') { $extra .= ' max="' . esc_attr($field['field_max']) . '"'; }
                }
                if ($type === 'date') {
                    foreach (['min_date'=>'min','max_date'=>'max'] as $key => $attr) { if (!empty($field[$key])) { $extra .= ' ' . $attr . '="' . esc_attr(substr((string) $field[$key], 0, 10)) . '"'; } }
                }
                if ($type === 'tel') { $extra .= ' pattern="[0-9()#&+*\-=.\s]+" inputmode="tel"'; }
                if ($type === 'email') { $extra .= ' autocomplete="email"'; }
                return '<input type="' . $html_type . '" class="obx-form__input' . $size . '" id="' . esc_attr($dom) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"' . $placeholder . $extra . $req . '>';
        }
    }

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $fields = array_values(array_filter((array) ($s['form_fields'] ?? []), 'is_array'));
        if (!$fields) { $this->placeholder('Añade al menos un campo al formulario.'); return; }
        // The popup opened after sending must be on the page.
        if (in_array('popup', (array) ($s['submit_actions'] ?? []), true) && ($s['popup_action'] ?? '') === 'open') { \OldBearExpansion\Popups\Popups::enqueue((int) ($s['popup_action_popup_id'] ?? 0)); }
        $document = \Elementor\Plugin::$instance->documents->get_current();
        $post_id = $document ? (int) $document->get_main_id() : (int) get_the_ID();
        $form_dom = (string) ($s['form_id'] ?? '') !== '' ? sanitize_html_class($s['form_id']) : '';
        $labels = ($s['show_labels'] ?? 'yes') !== '';
        $mark = $labels && ($s['mark_required'] ?? '') === 'yes';
        $messages = ($s['custom_messages'] ?? '') !== '' ? array_merge(self::messages(), array_filter(array_intersect_key($s, self::messages()), static fn($m) => (string) $m !== '')) : self::messages();

        // Steps: fields of type «step» start a new group.
        $steps = [];
        $current = null;
        foreach ($fields as $field) {
            if (($field['field_type'] ?? '') === 'step') { $steps[] = ['field'=>$field,'fields'=>[]]; $current = count($steps) - 1; continue; }
            if ($current === null) { $steps[] = ['field'=>null,'fields'=>[]]; $current = 0; }
            $steps[$current]['fields'][] = $field;
        }
        $steps = array_values(array_filter($steps, static fn($step) => $step['fields']));
        $multi = count($steps) > 1;

        printf('<form class="obx-form%s" method="post" action="%s" enctype="multipart/form-data" data-obx-form name="%s"%s%s data-messages="%s"%s>',
            $mark ? ' obx-form--mark-required' : '',
            esc_url(rest_url(Handler::ROUTE_NS . Handler::ROUTE)),
            esc_attr((string) ($s['form_name'] ?? '')),
            $form_dom !== '' ? ' id="' . esc_attr($form_dom) . '"' : '',
            ($s['form_validation'] ?? '') === 'custom' ? ' novalidate' : '',
            esc_attr((string) wp_json_encode($messages)),
            $multi ? ' data-steps="' . count($steps) . '"' : ''
        );
        echo '<input type="hidden" name="post_id" value="' . esc_attr((string) $post_id) . '">';
        echo '<input type="hidden" name="form_id" value="' . esc_attr($this->get_id()) . '">';
        echo '<input type="hidden" name="referer_title" value="' . esc_attr(wp_strip_all_tags((string) wp_get_document_title())) . '">';
        echo '<input type="hidden" name="queried_id" value="' . esc_attr((string) get_queried_object_id()) . '">';
        echo '<div class="obx-form__hp" aria-hidden="true"><label>Deja este campo vacío <input type="text" name="obx_hp" value="" tabindex="-1" autocomplete="off"></label></div>';

        if ($multi && ($s['step_type'] ?? 'number_text') !== 'none') { $this->render_indicators($steps, $s); }

        echo '<div class="obx-form__fields">';
        $prev_text = (string) ($s['step_previous_label'] ?? 'Anterior');
        $next_text = (string) ($s['step_next_label'] ?? 'Siguiente');
        $animation = ($s['button_hover_animation'] ?? '') ? ' elementor-animation-' . sanitize_html_class($s['button_hover_animation']) : '';
        $size = sanitize_html_class((string) ($s['button_size'] ?? 'sm'));
        foreach ($steps as $index => $step) {
            if ($multi) { echo '<div class="obx-form__step"' . ($index ? ' hidden' : '') . ' data-step="' . $index . '">'; }
            foreach ($step['fields'] as $field) {
                $type = (string) ($field['field_type'] ?? 'text');
                if (!in_array($type, self::TYPES, true)) { continue; }
                $id = self::field_id($field);
                $dom = 'form-field-' . $id . '-' . $this->get_id();
                if ($type === 'hidden') { echo $this->input($field, $id, $dom, $s); continue; }
                $required = ($field['required'] ?? '') === 'true';
                printf('<div class="obx-form__field obx-form__field--%1$s obx-form__field--%2$s %3$s%4$s">', esc_attr($type), esc_attr($id), esc_attr(self::col_classes($field)), $required ? ' is-required' : '');
                if ($type === 'html') {
                    echo wp_kses_post((string) ($field['field_html'] ?? ''));
                } else {
                    $label = (string) ($field['field_label'] ?? '');
                    if ($label !== '') {
                        $grouped = in_array($type, ['radio','checkbox'], true);
                        printf('<%1$s class="obx-form__label%2$s"%3$s>%4$s</%1$s>', $grouped ? 'span' : 'label', $labels ? '' : ' screen-reader-text', $grouped ? '' : ' for="' . esc_attr($dom) . '"', esc_html($label));
                    }
                    echo $this->input($field, $id, $dom, $s);
                    echo '<span class="obx-form__error" hidden></span>';
                }
                echo '</div>';
            }
            if ($multi) {
                echo '<div class="obx-form__field obx-form__actions obx-col-100">';
                if ($index > 0) { $prev = (string) ($step['field']['previous_button'] ?? '') ?: $prev_text; echo '<button type="button" class="obx-form__previous elementor-button elementor-size-' . esc_attr($size) . esc_attr($animation) . '">' . esc_html($prev) . '</button>'; }
                if ($index < count($steps) - 1) { $next = (string) ($steps[$index + 1]['field']['next_button'] ?? '') ?: (string) ($step['field']['next_button'] ?? '') ?: $next_text; echo '<button type="button" class="obx-form__next elementor-button elementor-size-' . esc_attr($size) . esc_attr($animation) . '">' . esc_html($next) . '</button>'; }
                else { $this->render_submit($s, $size, $animation); }
                echo '</div></div>';
            }
        }
        if (!$multi) {
            echo '<div class="obx-form__field obx-form__actions ' . esc_attr(self::col_classes($s, 'button_width')) . '">';
            $this->render_submit($s, $size, $animation);
            echo '</div>';
        }
        echo '</div><div class="obx-form__message" role="status" aria-live="polite" hidden></div></form>';
    }

    private function render_submit(array $s, string $size, string $animation): void {
        $icon = '';
        if (!empty($s['selected_button_icon']['value'])) {
            $icon = '<span class="elementor-button-icon">' . Icons_Manager::try_get_icon_html($s['selected_button_icon'], ['aria-hidden'=>'true']) . '</span>';
        }
        $direction = in_array($s['button_icon_align'] ?? '', ['right','row-reverse'], true) ? ' style="flex-direction: row-reverse"' : '';
        printf('<button type="submit" class="obx-form__submit elementor-button elementor-size-%1$s%2$s"%3$s><span class="elementor-button-content-wrapper"%4$s>%5$s<span class="elementor-button-text">%6$s</span></span></button>',
            esc_attr($size), esc_attr($animation),
            (string) ($s['button_css_id'] ?? '') !== '' ? ' id="' . esc_attr(sanitize_html_class($s['button_css_id'])) . '"' : '',
            $direction, $icon, esc_html((string) ($s['button_text'] ?? '')));
    }

    private function render_indicators(array $steps, array $s): void {
        $type = (string) ($s['step_type'] ?? 'number_text');
        if ($type === 'progress_bar') {
            echo '<div class="obx-form__progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . (int) round(100 / count($steps)) . '"><div class="obx-form__progress-fill" style="width:' . (int) round(100 / count($steps)) . '%">' . (int) round(100 / count($steps)) . '%</div></div>';
            return;
        }
        $shape = sanitize_html_class((string) ($s['step_icon_shape'] ?? 'circle'));
        echo '<ol class="obx-form__indicators obx-form__indicators--' . esc_attr($type) . ' obx-form__indicators--' . esc_attr($shape) . '">';
        foreach ($steps as $index => $step) {
            $label = (string) ($step['field']['field_label'] ?? '');
            $mark = '';
            if (str_contains($type, 'number')) { $mark = (string) ($index + 1); }
            if (str_contains($type, 'icon')) { $mark = !empty($step['field']['selected_icon']['value']) ? (string) Icons_Manager::try_get_icon_html($step['field']['selected_icon'], ['aria-hidden'=>'true']) : (string) ($index + 1); }
            echo '<li class="obx-form__indicator' . ($index ? '' : ' is-active') . '">';
            if ($mark !== '') { echo '<span class="obx-form__indicator-mark">' . ($type === 'icon' || $type === 'icon_text' ? $mark : esc_html($mark)) . '</span>'; }
            if ($label !== '' && str_contains($type, 'text')) { echo '<span class="obx-form__indicator-label">' . esc_html($label) . '</span>'; }
            echo '</li>';
        }
        echo '</ol>';
    }
}
