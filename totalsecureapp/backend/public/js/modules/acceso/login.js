$(document).ready(function () {

    ajax.setup();
    $('#aclg').on('submit', function (e) {
        e.preventDefault();
        var form = $(this).serialize();
        ajax.send(form, login_check, urlbase+'/acceso/login_check')
    });

    history.pushState(null, null, location.href);
    window.onpopstate = function(event) {
        history.go(1);
    };

});

$(document).on('click', '#btnEnviar', function () {

    $(".btn").attr('disabled', true);
    $.ajax({
        type: 'POST',
        url: urlbase + '/acceso/solicitud_cambiopass',
        data: $("#formpass").serialize(),
        success: function (data) {
            if ((data.errors)) {
                $.each(data.errors, function (index, value) {
                    toastr.error(value[0], 'Alerta de error', {timeOut: 10000});
                })
            } else {
                toastr.success('Se le ha enviado un correo con los pasos para cambiar su contraseña', 'Alerta exitosa', {timeOut: 5000});
                $('input:text').val("");
                $('select').val("");
            }
        },
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            toastr.error(errorThrown, textStatus, {timeOut: 5000});
        }
    }).always(function () {
        $(".btn").attr('disabled', false);
    });
});


var login_check = function(data) {
    if(data.errors){
        console.log(data.errors);
        mensaje_error('', data.errors);
    }else{
        location.href = urlbase + '/acceso/seleccionar_perfil';
    }
};
