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
