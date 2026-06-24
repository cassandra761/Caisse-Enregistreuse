function switchTab(tabName) {
    const tabs    = document.querySelectorAll('.tab-content');
    const buttons = document.querySelectorAll('.tab-button');
 
    // Cacher tous les contenus et désactiver tous les boutons
    tabs.forEach(t => t.classList.remove('active'));
    buttons.forEach(b => b.classList.remove('active'));
 
    // Activer le contenu demandé
    const activeTab = document.getElementById(tabName);
    if (activeTab) activeTab.classList.add('active');
 
    // Activer le bouton correspondant
    const activeBtn = document.querySelector(`.tab-button[data-tab="${tabName}"]`);
    if (activeBtn) activeBtn.classList.add('active');
}
 
// Exposer globalement (au cas où des onclick="" seraient encore utilisés)
window.switchTab = switchTab;
 
document.addEventListener('DOMContentLoaded', () => {
    const buttons = document.querySelectorAll('.tab-button');
 
    // Brancher les event listeners
    buttons.forEach(btn => {
        btn.addEventListener('click', () => {
            switchTab(btn.getAttribute('data-tab'));
        });
    });
 
    // CORRECTION 3 : activer le premier onglet par défaut au chargement
    // (le HTML met déjà class="active" sur le premier, mais on s'en assure ici)
    const firstBtn = document.querySelector('.tab-button[data-tab]');
    if (firstBtn && !document.querySelector('.tab-content.active')) {
        switchTab(firstBtn.getAttribute('data-tab'));
    }
});