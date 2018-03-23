/*
*/
// jshint undef: false, unused: false

function doStuff(hosts) {

    if (hosts == 0) {
        // Required to autopopulate the first list.
        var platformid = hosts;
    } else { 
        var platformid = hosts.options[hosts.selectedIndex].value;
    }

    var urlbase = M.cfg.wwwroot+'/blocks/publishflow/ajax/categorybuilder.php';

    $.post(
        urlbase,
        {
            platformid: platformid,
        },
        function(data, status) {

            var catArray = JSON.parse(data, null);

            var html = '<select  name="category"  size="1">';
            for (i = 0; i < catArray.length; i++) {
                html = html + '<option value=' + catArray[i].orid + '>' + catArray[i].name + '</option>';
            }
            html = html+'</select>';
            $('#category-div').html(html);
        }
    );
}