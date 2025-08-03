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
    // Quando o usuário seleciona um evento, atualiza a URL e busca automaticamente
$('#evento_id').on('change', function () {
    const selectedOption = $(this).find('option:selected');
    const eventId = selectedOption.val();
    const eventTitle = selectedOption.text();

    if (eventId) {
        // Gera slug do nome do evento
        const slug = eventTitle
            .toLowerCase()
            .normalize('NFD') // remove acentos
            .replace(/[\u0300-\u036f]/g, '') // remove combinações
            .replace(/[^a-z0-9]+/g, '-') // troca por hífen
            .replace(/^-+|-+$/g, ''); // limpa início/fim

        // Atualiza a query string da URL sem recarregar a página
        const params = new URLSearchParams(window.location.search);
        params.set('evento', slug);
        const novaUrl = `${window.location.pathname}?${params.toString()}`;
        window.history.pushState({}, '', novaUrl);

        buscarParticipantes();
    } else {
        $('#resultadoListaParticipantes').empty();
    }
});

});
