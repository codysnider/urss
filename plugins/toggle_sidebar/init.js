Plugins.Toggle_Sidebar = {
    toggle: function() {
        Feeds.toggle();

        const label = document.querySelector("i.toggle-sidebar-label");

        label.innerHTML = Element.visible("feeds-holder") ? 'chevron_left' : 'chevron_right';
    }
};
