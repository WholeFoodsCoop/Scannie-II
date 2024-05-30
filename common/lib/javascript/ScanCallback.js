/*
    var ScanCallbacks object
    A list of methods to execute using ScanCaseEval
*/
/*
    function ScanCaseAdd
    parameters: _case [string], fn function
    Call to add a method to ScanCallbacks

*/
/*
    function ScanCaseEval (
*/
var ScanCallbacks = {
   'default': [() => false]
};

function ScanCaseAdd(_case, fn) {

    let check = Object.entries(ScanCallbacks);
    check.forEach(items => {
        let casename = items[0];
        if (casename == _case) {
            // .. do nothing ..
        } else {
           ScanCallbacks[_case] = ScanCallbacks[_case] || [];
           ScanCallbacks[_case][0] = fn;
        }
    });

}

function ScanCaseEval(value) {
   if (ScanCallbacks[value]) {
      ScanCallbacks[value].forEach(function(fn) {
          fn();
      });
   }
}

ScanCaseAdd('test_case', function() {
   console.log('Scan Case Eval is functional.');
});

let a = ScanCaseEval('test_case');
console.log(a);

var ScanConfirm = function(text, _case, callback) {

    // add the case & called method to ScanCallbacks
    ScanCaseAdd(_case, callback);

    // create the prompt and add it to DOM
    let argumentText = arguments[0];
    console.log(argumentText);

    let alertElement = document.createElement("div");
    alertElement.style.width = '300px';
    alertElement.style.height = '300px';
    alertElement.style.position = 'fixed';
    alertElement.style.top = '50%';
    alertElement.style.left = '50%';
    alertElement.style.zIndex = '9999';
    alertElement.style.borderRadius = '3px';
    alertElement.style.background = "repeating-linear-gradient(#343A40,  #565E66, #343A40 5px)";

    alertElement.style.margin = '-150px 0 0 -150px';

    let msgElement = document.createElement("div");
    msgElement.style.padding = '10px';
    msgElement.style.width = '280px';
    msgElement.style.marginLeft = '10px';
    msgElement.style.marginTop = '10px';
    msgElement.style.background = 'rgba(255,255,255,0.9)';
    msgElement.style.position = 'relative';
    msgElement.style.borderRadius = '3px';
    msgElement.style.fontSize = '16px';
    msgElement.style.border = '1px solid black';
    msgElement.align = 'center';
    msgElement.innerHTML += argumentText;

    let confirmElement = document.createElement("button");
    confirmElement.style.background = 'rgba(255,255,255,0.9)';
    confirmElement.style.position = 'absolute';
    confirmElement.style.bottom = '10px';
    confirmElement.style.right = '10px';
    confirmElement.style.borderRadius = '3px';
    confirmElement.style.fontSize = '18px';
    confirmElement.align = 'center';
    confirmElement.innerHTML += 'YES';
    confirmElement.style.cursor = 'pointer';
    // On YES click, call method using ScanCaseEval
    confirmElement.addEventListener('click', function() { 
        ScanCaseEval(_case);
        $(this).parent().remove();
    }, false);

    let declineElement = document.createElement("button");
    declineElement.style.background = 'rgba(255,255,255,0.9)';
    declineElement.style.position = 'absolute';
    declineElement.style.bottom = '10px';
    declineElement.style.left = '10px';
    declineElement.style.borderRadius = '3px';
    declineElement.style.fontSize = '18px';
    declineElement.align = 'center';
    declineElement.innerHTML += 'NO';
    declineElement.style.cursor = 'pointer';
    declineElement.addEventListener('click', function() { 
        $(this).parent().remove();
    }, false);

    alertElement.append(msgElement);
    alertElement.append(confirmElement);
    alertElement.append(declineElement);

    $('body').prepend(alertElement);

    return false;
}
