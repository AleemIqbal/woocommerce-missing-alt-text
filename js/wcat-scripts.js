jQuery(document).ready(function($) {
    var progressBar = $('#wcat-progress-bar');
    var progressWrap = $('#wcat-progress-wrap');
    var updateButton = $('#wcat-update-alt-text');
    var totalImages = parseInt(progressWrap.data('total-images'));

    updateButton.on('click', function(e) {
        e.preventDefault();

        updateButton.prop('disabled', true);
        progressWrap.show();

        updateAltText(0);
    });

    function updateAltText(attachmentId) {
        $.ajax({
            type: 'POST',
            url: wcat_ajax_object.ajax_url,
            data: {
                action: 'wcat_update_alt_text',
                attachment_id: attachmentId,
            },
            success: function(response) {
                if (response.status === 'success') {
                    var progress = ((response.next_attachment_id / totalImages) * 100).toFixed(2);
                    progressBar.width(progress + '%');
                    updateAltText(response.next_attachment_id);
                } else if (response.status === 'complete') {
                    progressBar.width('100%');
                    alert('All images have been updated.');
                    updateButton.prop('disabled', false);
                    progressWrap.hide();
                    progressBar.width('0%');
                } else {
                    alert('An error occurred. Please try again.');
                    updateButton.prop('disabled', false);
                    progressWrap.hide();
                    progressBar.width('0%');
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                updateButton.prop('disabled', false);
                progressWrap.hide();
                progressBar.width('0%');
            },
        });
    }
});