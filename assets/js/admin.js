jQuery(document).ready(function($) {
    $('#doaction, #doaction2').on('click', function(e) {
        const bulkAction = $(this).prev('select').val();

        if (bulkAction !== 'delete_attributes') {
            return;
        }

        e.preventDefault();

        const postIds = [];
        $('input[name="post[]"]:checked').each(function() {
            postIds.push($(this).val());
        });

        if (postIds.length === 0) {
            alert('Please select at least one item.');
            return;
        }

        let processedCount = 0;

        const processPost = (postId) => {
            const postRow = $('#post-' + postId);
            const titleColumn = postRow.find('.title .row-title');
            
     
            if(titleColumn.find('.wpda-status').length === 0) {
                 titleColumn.after('<div class="wpda-status" style="color: #999; font-size: 12px; margin-left: 5px;">' + wpda_ajax.processing_message + '</div>');
            }

            $.ajax({
                url: wpda_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpda_delete_attributes',
                    post_id: postId,
                    nonce: wpda_ajax.nonce
                },
                success: function(response) {
                    const statusDiv = titleColumn.parent().find('.wpda-status');
                    if (response.success) {
                        statusDiv.text(wpda_ajax.done_message).css('color', 'green');
                    } else {
                        let errorMessage = response.data.message || 'Error';
                        statusDiv.text(errorMessage).css('color', 'red');
                    }
                },
                error: function() {
                    const statusDiv = titleColumn.parent().find('.wpda-status');
                    statusDiv.text('Request failed.').css('color', 'red');
                },
                complete: function() {
                    processedCount++;
                    if (processedCount < postIds.length) {
                        processPost(postIds[processedCount]);
                    } else {
                 
                        $('input[name="post[]"]:checked').prop('checked', false);
                    }
                }
            });
        };

        processPost(postIds[processedCount]);
    });
});
