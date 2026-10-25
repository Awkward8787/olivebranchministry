(function () {
    var button = document.querySelector("[data-load-map]");
    if (button) {
        button.addEventListener("click", function () {
            var slot = document.getElementById("map-slot");
            if (!slot || slot.querySelector("iframe")) {
                return;
            }
            var src = slot.getAttribute("data-map");
            if (!src) {
                return;
            }
            var frame = document.createElement("iframe");
            frame.className = "map-frame";
            frame.title = "Map of 1033 1st Street SE, Moultrie, Georgia";
            frame.src = src;
            frame.loading = "lazy";
            frame.referrerPolicy = "no-referrer";
            slot.appendChild(frame);
            button.hidden = true;
        });
    }

    document.querySelectorAll("[data-print]").forEach(function (printButton) {
        printButton.addEventListener("click", function () {
            window.print();
        });
    });

    var servicePick = document.querySelector("[data-fill-label]");
    if (servicePick) {
        servicePick.addEventListener("change", function () {
            var label = document.getElementById("attendance-label");
            var option = servicePick.options[servicePick.selectedIndex];
            if (!label || !option || label.value !== "") {
                return;
            }
            label.value = option.getAttribute("data-name") || "";
        });
    }
}());
