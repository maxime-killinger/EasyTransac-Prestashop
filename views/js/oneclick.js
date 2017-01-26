/* 
 * EasyTransac oneclick.js
 */
jQuery(function ($) {
    // Double load failsafe.
    var session_id = 'easytransac-oneclick' + Date.now();
    
    // Creates workspace.
    $('#easytransac-namespace').append($('<div id="'+session_id+'" class="payment_box payment_method_easytransac">'));

    // Unified OneClick loader
    // Requires : listcards_url
    //
    var listcards_url = location.protocol + '//' + document.domain + '/module/easytransac/listcards';
    var oneclick_url = location.protocol + '//' + document.domain + '/module/easytransac/oneclick';
    
    $('#'+session_id).html('<span id="etocloa001">OneClick loading ...</span>');
    
    // JSON Call
    $.getJSON(listcards_url, {}, buildFromJson);
    
    // Build  OneClick form from JSON.
    function buildFromJson(json) {
        
        $('#etocloa001').fadeOut().remove();
        
        if (!json.status || json.packet.length === 0) {
            
            // No cards available.
            $('#'+session_id).remove();
            return;
        }
        
        // Namespace
        var _space = $('#'+session_id);

        // Label
        _space.append($('<span style="width:100px;color: #5FAB8D;font-size: 20px;" title="Direct credit card payment">EasyTransac OneClick : </span>'));

        // Dropdown
        _space.append($('<select id="etalcadd001" name="oneclick_alias" style="width:200px; margin-left:10px;text-align:center;">'));
        $.each(json.packet, function (i, row) {
            $('#etalcadd001')
                .append($('<option value="' + row.Alias + '">' + row.CardNumber + '</option>'));
        });

        // Button
        _space.append($(' <input type="submit" id="etocbu001" class="button" style="width:150px; margin-left:15px;text-align:center;" value="OneClick pay">'));

        // Button click/*
        $('#etocbu001').click(function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            _space.hide();
            
            // Set Drupal Commerce fields.
            $('#easytransac_is_oneclick').val('yes');
            $('#easytransac_oneclick_card').val($('#etalcadd001 > option:selected').val());
            $(this).parents('form').submit();
            
            var payload = {Alias: $('#etalcadd001 > option:selected').val()};
            
            var _undisable = function(){
                $('#easytransac-waiting-room').remove();
                _space.fadeIn();
            };
            $('#easytransac-namespace').append($('<div id="easytransac-waiting-room" style="color:#AAA;font-size:26px;text-align:center;">Payment in progress...</div>'));
            // OneClick
            $.ajax({
                url: oneclick_url,
                data: payload,
                type: 'POST',
                dataType: 'json'
            }).done(function (data) {
                if(data.error === 'no'){
                    if(data.paid_status === 'processed') {
                        window.location.href = data.redirect_page;
                    } else {
                        alert('EasyTransac : Payment failed');
                        _undisable();
                    }
                } else {
                    alert('Payment Error: ' + data.message);
                    _undisable();
                }
            }).fail(_undisable);
        });
    }
});