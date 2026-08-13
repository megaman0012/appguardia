$(document).ready(function () {

    ajax.setup();
    $(document).on('click', '.btnPerfil', function (e) {
        e.preventDefault();
        var code = $(this).data('code');
        var params = { code:code };
        ajax.send(params, procesar_perfil, urlbase+'/acceso/procesar_perfil')
    });

    $('#acPerfLgBack').on('click', function (e) {
        e.preventDefault();
        location.href = urlbase + '/acceso/logout'
    });

});

var procesar_perfil = function(data) {
    if(data.errors){

    }else{
        location.href = urlbase + '/' + data.link;
    }
};
