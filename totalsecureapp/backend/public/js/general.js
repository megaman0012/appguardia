class ajax{
    static setup(){
        $.ajaxSetup({
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
        });
    }
    static send( params, action, url, type = 'POST' ){
        $.ajax({
            data: params,
            url:  url,
            type: type,
            beforeSend: function() {
                $('#asyncLoader').show();
                $('.btn').attr("disabled","");
            },
            success: function(data) {
                $('#asyncLoader').hide();
                $('.btn').removeAttr("disabled");
                action(data);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $('#asyncLoader').hide();
                $('.btn').removeAttr("disabled");
                const statusCode = jqXHR.status;
                if (jqXHR.responseJSON) {
                    const errorResponse = jqXHR.responseJSON;
                    let errorMessage = errorResponse.message || "Ha ocurrido un error desconocido";
                    toastr.error( errorMessage , 'StatusCode: '+statusCode, {timeOut: 5000});
                }else{
                    toastr.error(errorThrown, textStatus, {timeOut: 5000});
                }
            }
        });
    }
}

class dtable{
    static basic(id = '.datatable', data=''){
        if ( $.fn.DataTable.isDataTable(id) ) {
            $(id).DataTable().clear();
            $(id).DataTable().destroy();
        }
        $(id+' tbody').empty();
        $(id+' tbody').append(data);
        $(id).DataTable({
            processing: true,
            serverSide: false,
            searching: true,
            lengthChange: true,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "TODO"]],
            bSort: true,
        });
        $(id+" .pagination").addClass("flex-container");
    }
    static server(url, columnas, id = '.datatable', q = '#q', length = 10, type = 'GET') {
        $(id).DataTable({
            pageLength: length,
            processing: true,
            serverSide: true,
            ajax: {
                url: url,
                type: type,
                data: function(data) {
                    console.log("Datos enviados:", data);
                },
                error: function(xhr, error, thrown) {
                    console.error('Error en la solicitud AJAX:', thrown);
                }
            },
            columns: columnas,
            fixedColumns: true,
            responsive: true,
            searching: true,
            lengthChange: true,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "TODO"]],
            bSort: false,
            language: {
                "sProcessing": "<img style='width: 5%; margin-top: 5%;' src='" + urlbase + "/images/loading.gif'><p><b>Cargando Información</b></p>",
                "emptyTable": "SIN DATOS QUE MOSTRAR",
                "lengthMenu": "MOSTRANDO FILAS &nbsp; _MENU_",
                "decimal": ",",
                "thousands": ","
            },
            autoWidth: false,
            /*dom: 'lBfrtip',
            buttons: [{
                extend: 'collection',
                text: 'EXPORTAR',
                buttons: ['excel', 'csv', 'pdf']
            }]*/
        });
        $(id+" .pagination").addClass("flex-container");
    }
    static server_destroy(id = '.datatable'){
        if ( $.fn.DataTable.isDataTable(id) ) {
            $(id).DataTable().clear();
            $(id).DataTable().destroy();
        }
    }
    static server_reload(id = '.datatable') {
        $(id).DataTable().ajax.reload(null,false);
    }
}

function toastr_show( msg, title, notificationType = 'success'){
    toastr[notificationType](msg, title, {timeOut: 4000});
}

function mensaje_error(accion, errors) {
    var timeout = 5000;
    if (errors) {
        setTimeout(function () {
            //$('#' + accion + 'Modal').modal('show');
            $.each(errors, function (index, value) {
                toastr.error(value[0], 'Alerta de error', {timeOut: timeout});
                timeout += 1000;
            });
        }, 500);
    }
}

$(document).ready(function () {
    $(".datepicker").datepicker({
        dateFormat: "yy-mm-dd",
        maxDate: new Date(),
        changeYear: true,
    });
});

class inputs{
    static controlInsert(selector='textarea', limit=100){

        $('#'+selector).parent().append('<div id="'+selector+'Remaining" class="charRemaining">100 caracteres restantes</div>');
        function updateRemainingChars(selector) {
            var currentLength = $('#'+selector).val().length;
            var remainingChars = limit - currentLength;
            $('#'+selector+'Remaining').text(remainingChars + ' caracteres restantes');
        }
        $('#'+selector).on('input', function() {
            var currentLength = $(this).val().length;
            if (currentLength > limit) {
                $(this).val($(this).val().substring(0, limit));
            }
            updateRemainingChars(selector);
        });
        $('#'+selector).on('paste', function(e) {
            var clipboardData = e.originalEvent.clipboardData || window.clipboardData;
            var pastedText = clipboardData.getData('text');
            if (pastedText.length > limit) {
                pastedText = pastedText.substring(0, limit);
            }
            var currentText = $(this).val();
            var newText = currentText + pastedText;
            if (newText.length > limit) {
                newText = newText.substring(0, limit);
            }

            $(this).val(newText);
            updateRemainingChars(selector);
        });
        updateRemainingChars(selector);

    }
}
