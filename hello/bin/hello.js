(function(window) {
var main = function() {};

function _goodbye() {

var Bye = {
    greeting: 'Goodbye'
};


}
var goodbye = new _goodbye();

function _hello() {
// We can set up variables used by all objects here
var default_name = 'Homer';
// Or import libraries
var $J = jQuery;

/**
 * Our object that greets people.
 * Note that this object is currently "private" (i.e. cannot be accessed from
 * outside this package).
 */
var Greet = {
    sayHi: function(name) {
        name = name || default_name;
        alert('Hello, ' + name + '!');
    }
};

/**
 * Greeting in german.
 */
GreetGerman = {
    sayHi: function(name) {
        name = name || default_name;
        alert('Guten tag, ' + name + '!');
    }
};
// Make our objects "public" (accessible outside this package)
this.Greet = Greet;
this.German = GreetGerman;
}
var hello = new _hello();


main.goodbye = goodbye;
main.hello = hello;

window.hello = main;
return main;
})(window);
