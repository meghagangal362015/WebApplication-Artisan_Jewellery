// Cookie utilities
function setCookie(name, value, days) {
    var expires = "";
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + encodeURIComponent(value) + expires + "; path=/";
}

function getCookie(name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(";");
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) === " ") c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) {
            return decodeURIComponent(c.substring(nameEQ.length, c.length));
        }
    }
    return null;
}

// Recent products (last 5) -----------------------------
function getRecentProducts() {
    var raw = getCookie("recentProducts");
    if (!raw) {
        return [];
    }
    try {
        var parsed = JSON.parse(raw);
        if (Array.isArray(parsed)) {
            return parsed;
        }
    } catch (e) {
        // ignore parse errors
    }
    return [];
}

function trackProductVisit(productId, productName) {
    // Update recent products list
    var recent = getRecentProducts();
    recent = recent.filter(function (item) {
        return item.id !== productId;
    });
    recent.unshift({ id: productId, name: productName });
    if (recent.length > 5) {
        recent = recent.slice(0, 5);
    }
    setCookie("recentProducts", JSON.stringify(recent), 7);

    // Update visit counts
    updateVisitCount(productId);
}

// Visit counts (for most visited) ----------------------
function getVisitCounts() {
    var raw = getCookie("productVisitCounts");
    if (!raw) {
        return {};
    }
    try {
        var parsed = JSON.parse(raw);
        if (parsed && typeof parsed === "object") {
            return parsed;
        }
    } catch (e) {
        // ignore parse errors
    }
    return {};
}

function updateVisitCount(productId) {
    var counts = getVisitCounts();
    var current = counts[productId] || 0;
    counts[productId] = current + 1;
    setCookie("productVisitCounts", JSON.stringify(counts), 7);
}

function getMostVisitedProducts(limit) {
    var counts = getVisitCounts();
    var entries = [];
    for (var id in counts) {
        if (Object.prototype.hasOwnProperty.call(counts, id)) {
            entries.push({ id: id, count: counts[id] });
        }
    }
    entries.sort(function (a, b) {
        return b.count - a.count;
    });
    if (typeof limit === "number") {
        entries = entries.slice(0, limit);
    }

    // Try to find product names from recent list; fall back to ID
    var recent = getRecentProducts();
    return entries.map(function (entry) {
        var name = entry.id;
        for (var i = 0; i < recent.length; i++) {
            if (recent[i].id === entry.id && recent[i].name) {
                name = recent[i].name;
                break;
            }
        }
        return {
            id: entry.id,
            name: name,
            count: entry.count
        };
    });
}

