/**
 * Greeting in german.
 */
GreetGerman = {
    sayHi: function(name) {
        name = name || default_name;
        alert('Guten tag, ' + name + '!');
    }
};