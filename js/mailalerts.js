var oosHookJsCodeFunctions = [];

function oosHookJsCode() {
  for (var i = 0; i < oosHookJsCodeFunctions.length; i++) {
    if (typeof oosHookJsCodeFunctions[i] === 'function') {
      oosHookJsCodeFunctions[i]();
    }
  }
}

$(document).ready(function() {
  oosHookJsCode();
});
