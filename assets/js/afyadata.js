/* ---------------------------- */
/* XMLHTTPRequest Enable 	*/
/* ---------------------------- */
function createObject() {
    var request_type;

    var browser = navigator.appName;
    if (browser == "Microsoft Internet Explorer") {
        request_type = new ActiveXObject("Microsoft.XMLHTTP");
    } else {
        request_type = new XMLHttpRequest();
    }
    return request_type;
}

var http = createObject();

//suggest level
function suggest_form() {
    form_id = document.getElementById('form_id').value;

    // Set te random number to add to URL request
    var base_url = document.getElementById('base_url').value;
    nocache = Math.random();
    http.open('POST', base_url + 'xform/get_search_field/' + form_id);

    http.onreadystatechange = suggest_reply_form;
    http.send(null);
}

function suggest_reply_form() {
    if (http.readyState == 4) {
        var response = http.responseText;
        e = document.getElementById('search_field');
        if (response != "") {
            e.innerHTML = response;
            e.style.display = "block";
        } else {
            e.style.display = "none";
        }
    }
}

//suggest facilities
function suggest_facilities() {
    district_id = document.getElementById('district').value;

    // Set te random number to add to URL request
    var base_url = document.getElementById('base_url').value;
    nocache = Math.random();
    http.open('POST', base_url + 'auth/get_facilities/' + district_id);

    http.onreadystatechange = suggest_reply_facilities;
    http.send(null);
}

function suggest_reply_facilities() {
    if (http.readyState == 4) {
        var response = http.responseText;
        e = document.getElementById('facility');
        if (response != "") {
            e.innerHTML = response;
            e.style.display = "block";
        } else {
            e.style.display = "none";
        }
    }
}

//delete function
$(document).ready(function () {

    $("a.delete").click(function (e) {

        var confirmDelete = confirm("Are you sure you want to delete?");

        if (confirmDelete) {
            return true;
        } else {
            e.preventDefault();
        }

    });
});
