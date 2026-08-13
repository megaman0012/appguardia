$(document).ready(function () {

    let dxCounter = 1;
    $('#btnAddDx').click(function() {
        if(dxCounter>5){ toastr_show( 'No se puede agregar mas de 6 diagnosticos', 'Notificacion', 'error'); return; }
        dxCounter++;
        let newDx = $('#dx1').clone();
        newDx.attr('id', 'dx' + dxCounter);
        newDx.find('.number').html(dxCounter);
        newDx.find('input').val('');
        newDx.find('select').prop('selectedIndex', 0);
        $('#dxs').append(newDx);
    });

    $('#rf_type').change(function (e) {
        e.preventDefault();
        var rt = $(this).val();
        cambio_tipo_documento(rt);
    });



});

function cambio_tipo_documento(tipo){
    var divCont = $('#dv_rf_est_sal');
    if( tipo == 3 || tipo == 4 ){
        divCont.removeClass('col-sm-4');
        divCont.addClass('col-sm-2');
        $('#change_legend').html("Contrarefiere o Referencia Inversa a:");
        $('.section1').hide();
        $('.section1 input').val('');
        $('.section2').show();
    }else{
        divCont.removeClass('col-sm-2');
        divCont.addClass('col-sm-4');
        $('#change_legend').html("Refiere o Deriva a:");
        $('.section2').hide();
        $('.section2 input').val('');
        $('.section1').show();
    }
}

125469874199999
