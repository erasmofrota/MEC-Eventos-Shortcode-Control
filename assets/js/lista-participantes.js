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

    // Verifica a URL ao carregar e pré-seleciona o evento pelo slug
    // Verifica a URL e aguarda o <select> estar populado para pré-selecionar o evento
    const urlParams = new URLSearchParams(window.location.search);
    const slugEvento = urlParams.get('evento');

    if (slugEvento) {
        let tentativas = 0;
        const intervalo = setInterval(() => {
            let encontrado = false;

            $('#evento_id option').each(function () {
                const texto = $(this).text().toLowerCase()
                    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');

                if (texto === slugEvento) {
                    $(this).prop('selected', true);
                    buscarParticipantes();
                    encontrado = true;
                    clearInterval(intervalo);
                    return false;
                }
            });

            tentativas++;
            if (!encontrado && tentativas >= 1) {
                clearInterval(intervalo);
                console.warn('Evento da URL não encontrado após 2s.');
            }
        }, 20); // tenta a cada 20ms por até 2 segundos
    }


    // Submete manualmente
    $('#filtroForm').on('submit', function (e) {
        e.preventDefault();
        buscarParticipantes();
    });

    // Quando o usuário seleciona um evento
    $('#evento_id').on('change', function () {
        const selectedOption = $(this).find('option:selected');
        const eventId = selectedOption.val();
        const eventTitle = selectedOption.text();

        if (eventId) {
            const slug = eventTitle
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');

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
