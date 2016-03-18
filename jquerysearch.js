/*
* very simple code snippet that uses jQuery to fetch and dispaly a page of 
* thumbanils from a single Europeana API call
*
* you must include jQuery in your html page, plus <div id="content"></div> in the body
*
* a demo with the full code can be found at http://jsfiddle.net/jamesinealing/wu4628wk/
*/

var apiCall = 'http://www.europeana.eu/api/v2/search.json?wskey=api2demo&query=europeana_collectionName%3A9200434*&start=1&rows=24&profile=rich';
$.getJSON(apiCall, function (json) {
    // here we will do something with the response
    var totalcount = json.totalResults;
    var counthtml = '<h2>Total results: ' + totalcount + '</h2>';
    $('#content').append(counthtml);
    $.each(json.items, function (i, item) {
        var title = item.title;
        var link = item.guid;
        var thumbnail = item.edmPreview;
        var objecthtml = '';
        objecthtml += '<a href="' + link + '" class="thumbnail" title="' + title + '" target="_blank"><img src="' + thumbnail + '" alt="' + title + '"></a>';
        $('#content').append(objecthtml);
    });
});
