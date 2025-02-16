jQuery(document).ready(function($) {
    $('#sync-metas').click(function() {
        var formData = $('#duplicate-metas-form').serialize();

        $('#sync-metas').prop('disabled', true).text('Sincronizando...');
        $('#sync-loader').show();
        $('#sync-result').html('');

        $.ajax({
            type: "POST",
            url: duplicateMetasAjax.ajaxurl,
            data: formData + '&action=duplicate_metas_ajax',
            success: function(response) {
                $('#sync-metas').prop('disabled', false).text('Ejecutar Sincronización');
                $('#sync-loader').hide();

                if (response.success) {
                    $('#sync-result').html('<strong style="color: green;">' + response.data.message + '</strong>');
                } else {
                    console.error(response.data.message); // Solo se muestra en la consola
                    $('#sync-result').html('<strong style="color: red;">Ocurrió un error en la ejecución de PHP o WordPress.</strong>');
                }
            },
            error: function(xhr, status, error) {
                $('#sync-metas').prop('disabled', false).text('Ejecutar Sincronización');
                $('#sync-loader').hide();
                console.error(error); // Solo en la consola
                $('#sync-result').html('<strong style="color: red;">Error crítico: ' + error + '</strong>');
            }
        });
    });
});
