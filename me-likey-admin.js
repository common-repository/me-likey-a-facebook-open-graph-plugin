jQuery(document).ready(function($) {
    var ml_url = escape('http://www.jqueryin.com/2010/04/23/me-likey-a-facebook-open-graph-wordpress-plugin/');
    
    // watch for preview click
    $('#me-likey-preview').click(function(e) {
        var faces, w, h, bgcolor, iframe = [];
        faces = $('#me_likey_faces').val();
        faces = (faces == '1') ? 'true' : 'false';
        bgcolor = $('#me_likey_color').val();
        bgcolor = (bgcolor == 'light') ? '#FFFFFF' : '#333333';
        w = $('#me_likey_width').val();
        w = w.replace('px', '');
        h = $('#me_likey_height').val();
        h = h.replace('px', '');
        
        
        // populate iframe values
        iframe.push('<iframe src="http://www.facebook.com/plugins/like.php?href='+ ml_url);
        iframe.push('&amp;layout='+ $('#me_likey_layout').val());
        iframe.push('&amp;show_faces='+ faces);
        iframe.push('&amp;width='+ w);
        iframe.push('&amp;height='+ h);
        iframe.push('&amp;action='+ $('#me_likey_verb').val());
        iframe.push('&amp;font=' + $('#me_likey_font').val());
        iframe.push('&amp;colorscheme=' + $('#me_likey_color').val() + '" ');
        iframe.push('scrolling="no" frameborder="0" allowTransparency="true" ');
        iframe.push('class="' + $('#me_likey_class').val() + '" style="border:none;overflow:hidden;');
        iframe.push('width:' + w + 'px;height:' + h + 'px;">');
        iframe.push('</iframe>');
        
        // push HTML
        $('#me-likey-preview-window').css('background-color', bgcolor).show().html(iframe.join(''));
        
        e.preventDefault();
        return false;
    });
});