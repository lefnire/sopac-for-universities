
// Collapses large tables of book status/locations, with a "show more" link
Drupal.behaviors.sopacCollapseLocationsTable = function (context) {
    var max_rows = 3;
    var table = $("table#sopac-status-location");
    var rows = table.find("tr");
    
    if( rows.length > (max_rows-1) ){
      var tbody = rows
        .slice(max_rows)
        .wrapAll("<tbody id='sopac-hidden-locations'></tbody>")
        .parent()
          .appendTo( table )
          .hide();
          
      var show_text = "[+] Show all locations ("+ (rows.length-max_rows) +")";
      var hide_text = "[-] Hide all locations ("+ (rows.length-max_rows) +")";
      table
        .after("<input id='sopac-show-locations' type='button' value='"+ show_text +"'/>")
        .next()
          .click(function() {
            if(tbody.is(":visible")){
              $(this).val(show_text);
            }else{
              $(this).val(hide_text);
            }
            tbody.toggle();
          });
    }
};