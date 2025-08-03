jQuery(document).ready(function ($) {
    function buscarParticipantes() {
        $('#loader').show();
        $('#resultadoListaParticipantes').empty();

        $.post(listaParticipantesAjax.ajaxurl, {
            action: 'filtro_lista_participantes',
            nonce: listaParticipantesAjax.nonce,
            evento_id: $('#evento_id').val(),
            nome: $('#nome').val(),
            categoria: $('#categoria').val()
        }, function (response) {
            $('#loader').hide();
            if (response.success) {
                $('#resultadoListaParticipantes').html(response.data.html);
            } else {
                $('#resultadoListaParticipantes').html('<p>Erro ao buscar participantes.</p>');
            }
        });
    }

    // Submeter o formulário
    $('#filtroForm').on('submit', function (e) {
        e.preventDefault();
        buscarParticipantes();
    });

    // Quando o usuário seleciona um evento, busca automaticamente
    $('#evento_id').on('change', function () {
        if ($(this).val()) {
            buscarParticipantes();
        } else {
            $('#resultadoListaParticipantes').empty();
        }
    });
});
