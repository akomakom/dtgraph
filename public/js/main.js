//hi!

Date.prototype.addYears = function(n) {
    var now = new Date();
    return new Date(now.setFullYear(now.getFullYear() + n));
};

Date.prototype.addDays = function(n) {
    var now = new Date();
    return new Date(now.getTime() + (n * 86400000));
};

Date.prototype.addHours = function(n) {
    var now = new Date();
    return new Date(now.getTime() + (n * 3600000));
};