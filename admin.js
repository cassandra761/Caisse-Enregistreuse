document.addEventListener('DOMContentLoaded', () => {

    const tabs = document.querySelectorAll('.tab-content');
    const buttons = document.querySelectorAll('.tab-button');

    function switchTab(tabName) {

        // cacher tous les contenus
        tabs.forEach(t => t.classList.remove('active'));

        // désactiver tous les boutons
        buttons.forEach(b => b.classList.remove('active'));

        // activer contenu
        const activeTab = document.getElementById(tabName);
        if (activeTab) activeTab.classList.add('active');

        // activer bouton
        const activeBtn = document.querySelector(`.tab-button[data-tab="${tabName}"]`);
        if (activeBtn) activeBtn.classList.add('active');
    }

    // event listeners
    buttons.forEach(btn => {
        btn.addEventListener('click', () => {
            const tabName = btn.getAttribute('data-tab');
            switchTab(tabName);
        });
    });

});