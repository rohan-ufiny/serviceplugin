jQuery(function($){
    var form = $('#service-booking-form');
    var step = 1;

    function showStep(n){
        step = n;
        form.find('.sb-step').hide();
        form.find('.sb-step-' + step).show();
        $('#sb-progress .bar').removeClass('active');
        $('#sb-progress .step' + step).addClass('active');
    }

    form.on('click','.sb-next',function(e){
        e.preventDefault();
        if(step === 2){
            loadTrainers();
        }
        if(step === 3){
            buildSummary();
        }
        showStep(step+1);
    });

    form.on('click','.sb-prev',function(e){
        e.preventDefault();
        showStep(step-1);
    });

    function loadTrainers(){
        var service = $('#service').val();
        var date = $('#date').val();
        var time = $('#time').val();
        if(service && date && time){
            $.post(serviceBooking.ajax_url,{ action:'get_available_trainers', nonce: serviceBooking.nonce, service: service, date: date, time: time }, function(resp){
                var html = '<label for="trainer">Trainer:</label><select id="trainer" name="trainer">';
                if(resp.length){
                    $.each(resp,function(i,t){
                        var rating = t.rating ? ' ('+t.rating+'/5)' : '';
                        html += '<option value="'+t.id+'">'+t.name+rating+'</option>';
                    });
                } else {
                    html += '<option value="">No trainers available</option>';
                }
                html += '</select>';
                $('#trainer-container').html(html);
            });
        }
    }

    function buildSummary(){
        var serviceText = $('#service option:selected').text();
        var date = $('#date').val();
        var time = $('#time').val();
        var trainerText = $('#trainer option:selected').text();
        var html = '<p><strong>Service:</strong> '+serviceText+'</p>'+
                   '<p><strong>Date:</strong> '+date+'</p>'+
                   '<p><strong>Time:</strong> '+time+'</p>'+
                   '<p><strong>Trainer:</strong> '+trainerText+'</p>';
        $('#booking-summary').html(html);
    }

    form.on('submit', function(e){
        e.preventDefault();
        $.post(serviceBooking.ajax_url, {
            action: 'create_booking',
            nonce: serviceBooking.nonce,
            service: $('#service').val(),
            date: $('#date').val(),
            time: $('#time').val(),
            trainer: $('#trainer').val()
        }, function(resp){
            if(resp.success){
                window.location = resp.data.redirect;
            } else {
                alert(resp.data);
            }
        });
    });

    showStep(1);
});
