<?php
/*
Plugin Name: MEC Eventos Shortcode Control
Description: Controla quais eventos do MEC podem ser listados por meio de shortcode.
Version: 1.3
Author: Erasmo Frota
*/

register_activation_hook(__FILE__, 'mec_esc_create_table');

function mec_esc_create_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'mec_eventos_autorizados';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY post_id (post_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

add_action('wp_enqueue_scripts', 'mec_esc_enqueue_styles');

function mec_esc_enqueue_styles()
{
    // Só carrega o CSS se o shortcode estiver na página
    if (is_singular() && has_shortcode(get_post()->post_content, 'lista_participantes_detalhada_filtro')) {
        wp_enqueue_style('lista-eventos-mec-1', plugin_dir_url(__FILE__) . 'assets/css/lista-eventos-mec-1.css', [], '1.0');
    }
    if (is_singular() && has_shortcode(get_post()->post_content,  'lista_participantes_agrupados_categoria')) {
        wp_enqueue_style('lista-eventos-mec-1', plugin_dir_url(__FILE__) . 'assets/css/lista-eventos-mec-1.css', [], '1.0');
    }
}

add_action('wp_enqueue_scripts',  'bootstrap_styles');

function bootstrap_styles()
{
    // Só carrega o CSS se o shortcode estiver na página
    if (is_singular() && has_shortcode(get_post()->post_content, 'lista_participantes_detalhada_filtro')) {
        wp_enqueue_style('bootstrap', plugin_dir_url(__FILE__) . 'assets/lib/bootstrap/css/bootstrap.css', [], '1.0');
    }
    if (is_singular() && has_shortcode(get_post()->post_content, 'lista_participantes_agrupados_categoria')) {
        wp_enqueue_style('bootstrap', plugin_dir_url(__FILE__) . 'assets/lib/bootstrap/css/bootstrap.css', [], '1.0');
    }
}


add_action('admin_menu', function () {
    add_menu_page('Eventos MEC', 'Eventos MEC', 'manage_options', 'mec-eventos', 'mec_esc_render_admin', 'dashicons-calendar-alt');
});

function mec_esc_render_admin()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'mec_eventos_autorizados';

    // Salvar eventos permitidos
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('mec_esc_save_events')) {
        $wpdb->query("TRUNCATE TABLE $table_name");
        if (!empty($_POST['eventos'])) {
            foreach ($_POST['eventos'] as $event_id) {
                $wpdb->insert($table_name, ['post_id' => intval($event_id)]);
            }
        }
        echo '<div class="updated"><p>Eventos atualizados com sucesso!</p></div>';
    }

    // Buscar todos os eventos
    $eventos = $wpdb->get_results("
        SELECT me.post_id, p.post_title FROM {$wpdb->prefix}mec_events me
        JOIN {$wpdb->prefix}posts p ON me.post_id = p.ID
        WHERE p.post_status = 'publish'
        ORDER BY p.post_title ASC
    ");

    // Buscar eventos autorizados
    $autorizados = $wpdb->get_col("SELECT post_id FROM $table_name");

    echo '<div class="wrap"><h1>Selecionar eventos permitidos</h1>';
    echo '<form method="post">';
    wp_nonce_field('mec_esc_save_events');
    echo '<table class="form-table"><tr><th>Eventos</th><td>';
    echo '<select name="eventos[]" multiple size="10" style="width: 100%;">';
    foreach ($eventos as $evento) {
        $selected = in_array($evento->post_id, $autorizados) ? 'selected' : '';
        echo "<option value='{$evento->post_id}' $selected>{$evento->post_title}</option>";
    }
    echo '</select>';
    echo '</td></tr></table>';
    echo '<p><input type="submit" class="button-primary" value="Salvar"></p>';
    echo '</form></div>';
}

function mec_esc_get_allowed_events()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'mec_eventos_autorizados';
    return $wpdb->get_col("SELECT post_id FROM $table_name");
}


add_shortcode(
    'lista_participantes_detalhada_filtro',
    function () {
        ob_start();
?>
    <form id="filtroForm">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6 col-sm-12"><label for="evento_id">Evento:</label>
                    <select name="evento_id" id="evento_id">
                        <option value="">-- Selecione um evento --</option>
                        <?php
                        global $wpdb;
                        $eventos = $wpdb->get_results("
                SELECT p.ID as post_id, p.post_title
                FROM {$wpdb->prefix}mec_events AS me
                JOIN {$wpdb->prefix}posts AS p ON me.post_id = p.ID
                WHERE me.post_id IN (SELECT post_id FROM {$wpdb->prefix}mec_eventos_autorizados)
                ORDER BY me.post_id DESC
            ");
                        foreach ($eventos as $evento) {
                            echo "<option value='{$evento->post_id}'>" . esc_html($evento->post_title) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-lg-4 col-md-6 col-sm-12">
                    <label for="nome">Nome do Participante:</label>
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="mec-sl-magnifier"></i></span>
                        <input class="form-control" type="search" name="nome" id="nome" />
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 col-sm-12"><label for="categoria">Categoria:</label>
                    <select name="categoria" id="categoria">
                        <option value="">--Todas--</option>
                    </select>
                </div>
            </div>
            <button type="submit">Buscar</button>


        </div>
    </form>


    <div id="resultado_participantes" style="margin-top: 20px;">

    </div>

    <script>
        jQuery(document).ready(function($) {
            function carregarParticipantes() {
                const eventoId = $('#evento_id').val();
                const nome = $('#nome').val();
                const categoria = $('#categoria').val();

                if (!eventoId) {
                    $('#resultado_participantes').html('<p>Por favor, selecione um evento para exibir os participantes.</p>');
                    return;
                }

                $.ajax({
                    url: '<?php echo admin_url("admin-ajax.php"); ?>',
                    type: 'GET',
                    data: {
                        action: 'carregar_participantes_detalhada',
                        evento_id: eventoId,
                        nome: nome,
                        categoria: categoria
                    },
                    beforeSend: function() {
                        $('body').append(`
                        <div class="loading-overlay">
                            <div class="spinner"></div>
                        </div>
                    `);
                    },
                    success: function(response) {
                        $('.loading-overlay').remove();
                        $('#resultado_participantes').html(response);

                        // Atualizar select de categorias
                        const categorias = [...new Set($('#resultado_participantes td:nth-child(3)').map(function() {
                            return $(this).text().trim();
                        }).get())];

                        $('#categoria').empty().append(`<option value="">-- Todas --</option>`);
                        categorias.forEach(c => {
                            $('#categoria').append(`<option value="${c}">${c}</option>`);
                        });
                    },
                    error: function() {
                        $('.loading-overlay').remove();
                        $('#resultado_participantes').html('<p>Erro ao carregar os dados.</p>');
                    }
                });

            }

            $('#evento_id').on('change', carregarParticipantes);
            $('#filtroForm').on('submit', function(e) {
                e.preventDefault();
                carregarParticipantes();
            });
        });
    </script>
<?php
        return ob_get_clean();
    }
);

add_action('wp_ajax_carregar_participantes_detalhada', 'ajax_carregar_participantes_detalhada');
add_action('wp_ajax_nopriv_carregar_participantes_detalhada', 'ajax_carregar_participantes_detalhada');

function ajax_carregar_participantes_detalhada()
{
    global $wpdb;

    $evento_id = isset($_GET['evento_id']) ? intval($_GET['evento_id']) : 0;
    $nome_pesquisa = isset($_GET['nome']) ? sanitize_text_field($_GET['nome']) : '';
    $categoria_pesquisa = isset($_GET['categoria']) ? sanitize_text_field($_GET['categoria']) : '';


    if (!$evento_id) {
        echo '<p>Evento não informado.</p>';
        wp_die();
    }

    $query = "
        SELECT DISTINCT 
            mb.id AS booking_id,
            usuario.display_name AS nome_usuario, 
            mb.confirmed AS confirmacao, 
            opt.option_value 
        FROM {$wpdb->prefix}mec_bookings AS mb
        JOIN {$wpdb->prefix}mec_booking_attendees AS mba ON mb.id = mba.mec_booking_id
        JOIN {$wpdb->prefix}options AS opt ON mb.transaction_id = opt.option_name
        LEFT JOIN {$wpdb->prefix}users AS usuario ON mba.user_id = usuario.ID
        WHERE mb.event_id = %d
    ";

    $parametros = [$evento_id];

    if (!empty($nome_pesquisa)) {
        $query .= " AND usuario.display_name LIKE %s";
        $parametros[] = '%' . $wpdb->esc_like($nome_pesquisa) . '%';
    }

    $resultados = $wpdb->get_results($wpdb->prepare($query, ...$parametros));

    if (!$resultados) {
        echo '<p>Nenhum participante encontrado.</p>';
        wp_die();
    }

    $evento_link = get_permalink($evento_id);
    $evento_titulo = get_the_title($evento_id);
    echo "<p><strong>Evento:</strong> <a href='{$evento_link}' class='link' target='_blank'>{$evento_titulo}</a></p>";


    echo '<table border="1" cellpadding="5" cellspacing="0">';
    echo '<tr><th>Nome(s)</th><th>Equipe</th><th>Categoria</th><th  class="column-center">Status</th></tr>';

    $ids_processados = [];

    $linhas_participantes = [];

    foreach ($resultados as $linha) {
        if (in_array($linha->booking_id, $ids_processados)) continue;
        $ids_processados[] = $linha->booking_id;

        $confirmado = $linha->confirmacao == 1 ? 'Confirmado' : 'Não';

        $dados = @unserialize($linha->option_value);
        if ($dados && isset($dados['tickets']) && is_array($dados['tickets'])) {
            foreach ($dados['tickets'] as $ticket) {
                $nome = trim($ticket['name'] ?? '-');
                $equipe = trim($ticket['reg'][8] ?? '-');
                $categoria = trim($ticket['reg'][11] ?? '-');

                // Filtro de categoria
                if (!empty($categoria_pesquisa) && strtolower($categoria) !== strtolower($categoria_pesquisa)) {
                    continue;
                }

                $linhas_participantes[] = [
                    'nome' => $nome,
                    'equipe' => $equipe,
                    'categoria' => $categoria,
                    'confirmado' => $confirmado
                ];
            }
        }
    }

    // Ordenar por nome
    usort($linhas_participantes, fn($a, $b) => strcasecmp($a['nome'], $b['nome']));

    // Exibir
    foreach ($linhas_participantes as $linha) {
        echo '<tr>';
        echo "<td>{$linha['nome']}</td>";
        echo "<td>{$linha['equipe']}</td>";
        echo "<td>{$linha['categoria']}</td>";
        echo '<td class="column-center"><span class="confirmar">' . $linha['confirmado'] . '</span></td>';
        echo '</tr>';
    }



    echo '</table>';
    wp_die();
}

add_shortcode('lista_participantes_agrupados_categoria', function () {
    ob_start();

    global $wpdb;

    // Formulário de filtro
    ?>
    <form id="filtroForm" method="post">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <label for="evento_id">Evento:</label>
                    <select name="evento_id" id="evento_id">
                        <option value="">-- Selecione um evento --</option>
                        <?php
                        $eventos = $wpdb->get_results("
                            SELECT p.ID as post_id, p.post_title
                            FROM {$wpdb->prefix}mec_events AS me
                            JOIN {$wpdb->prefix}posts AS p ON me.post_id = p.ID
                            WHERE me.post_id IN (
                                SELECT post_id FROM {$wpdb->prefix}mec_eventos_autorizados
                            )
                            ORDER BY me.post_id DESC
                        ");
                        foreach ($eventos as $evento) {
                            echo "<option value='{$evento->post_id}'>" . esc_html($evento->post_title) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <label for="nome">Nome do Participante:</label>
                     <div class="input-group mb-3">
                        <span class="input-group-text"><i class="mec-sl-magnifier"></i></span>
                        <input class="form-control" type="search" name="nome" id="nome" />
                    </div>
                </div>
                <!--<div class="col-lg-4 col-md-6 col-sm-12">-->
                <!--    <label for="categoria">Categoria:</label>-->
                <!--    <select name="categoria" id="categoria">-->
                <!--        <option value="">--Todas--</option>-->
                <!--    </select>-->
                <!--</div>-->
            </div>
            <button type="submit">Buscar</button>
        </div>
    </form>
    <div id="loader" style="display:none; text-align: center;">
    <img src="<?php echo plugins_url('assets/img/loader.gif', __FILE__); ?>" alt="Carregando" style="width: 50px;" />
    <p>Carregando...</p>
</div>

    <div id="resultadoListaParticipantes" style="margin-top: 20px;"></div>
    <?php

    // Enfileirar JS para tratar o AJAX
    wp_enqueue_script('lista-participantes-js', plugins_url('assets/js/lista-participantes.js', __FILE__), ['jquery'], null, true);
    wp_localize_script('lista-participantes-js', 'listaParticipantesAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('filtro_participantes_nonce')
    ]);

    return ob_get_clean();
});

add_action('wp_ajax_filtro_lista_participantes', 'mec_esc_filtrar_participantes');
add_action('wp_ajax_nopriv_filtro_lista_participantes', 'mec_esc_filtrar_participantes');

function mec_esc_filtrar_participantes() {
    check_ajax_referer('filtro_participantes_nonce', 'nonce');

    $evento_id = intval($_POST['evento_id']);
    $nome_filtro = sanitize_text_field($_POST['nome']);
    $categoria_filtro = sanitize_text_field($_POST['categoria']);

    global $wpdb;

    if (!$evento_id) {
        wp_send_json_error(['html' => '<p>Selecione um evento válido.</p>']);
        return;
    }

    $query = "
        SELECT DISTINCT 
            mb.id AS booking_id,
            usuario.display_name AS nome_usuario, 
            mb.confirmed AS confirmacao, 
            opt.option_value 
        FROM {$wpdb->prefix}mec_bookings AS mb
        JOIN {$wpdb->prefix}mec_booking_attendees AS mba ON mb.id = mba.mec_booking_id
        JOIN {$wpdb->prefix}options AS opt ON mb.transaction_id = opt.option_name
        LEFT JOIN {$wpdb->prefix}users AS usuario ON mba.user_id = usuario.ID
        WHERE mb.event_id = %d
    ";

    

    $resultados = $wpdb->get_results($wpdb->prepare($query, $evento_id));
    if (!$resultados) {
        wp_send_json_success(['html' => '<p>Nenhum participante encontrado.</p>']);
        return;
    }

    ob_start();

    $evento_link = get_permalink($evento_id);
    $evento_titulo = get_the_title($evento_id);
    echo "<p><strong>Evento:</strong> <a href='{$evento_link}' class='link' target='_blank'>{$evento_titulo}</a></p>";

    

    $linhas_por_categoria = [];

    foreach ($resultados as $linha) {
        $dados = @unserialize($linha->option_value);
        $confirmado = $linha->confirmacao == 1 ? 'Confirmado' : 'Não';

        if ($dados && isset($dados['tickets']) && is_array($dados['tickets'])) {
            foreach ($dados['tickets'] as $ticket) {
                $nome = trim($ticket['name'] ?? '-');
                $equipe = trim($ticket['reg'][8] ?? '-');
                $categoria = trim($ticket['reg'][11] ?? 'Não informada');

                // Aplicar filtros
                if ($nome_filtro && stripos($nome, $nome_filtro) === false) continue;
                if ($categoria_filtro && stripos($categoria, $categoria_filtro) === false) continue;

                $linhas_por_categoria[$categoria][] = [
                    'nome' => $nome,
                    'equipe' => $equipe,
                    'confirmado' => $confirmado
                ];
            }
        }
    }

   

    foreach ($linhas_por_categoria as $categoria => $participantes) {
        echo "<h4>Categoria: {$categoria}</h4>";
        echo '<table border="1" cellpadding="5" cellspacing="0">';
        echo '<tr><th>Nome(s)</th><th>Equipe</th><th>Status</th></tr>';

        usort($participantes, fn($a, $b) => strcasecmp($a['nome'], $b['nome']));

        foreach ($participantes as $p) {
            echo '<tr>';
            echo "<td>{$p['nome']}</td>";
            echo "<td>{$p['equipe']}</td>";
            echo '<td class="column-center"><span class="confirmar">' . $p['confirmado'] . '</span></td>';
            echo '</tr>';
        }

        echo '</table><br>';
    }

    $html = ob_get_clean();
wp_send_json_success(['html' => $html]);

}


?>