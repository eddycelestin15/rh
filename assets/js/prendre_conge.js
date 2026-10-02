(function () {
    let maxJours = 0;

    window.selectDecision = function (el, id, max, year) {
        document.querySelectorAll('.card').forEach(c => c.classList.remove('active'));
        el.classList.add('active');
        
        const formBox = document.getElementById('formBox');
        if (formBox) formBox.style.display = 'block';
        
        document.getElementById('decision_id').value = id;
        document.getElementById('lblYear').innerText = year;
        document.getElementById('limitMsg').innerText = "Maximum autorisé sur cette décision : " + max + " jours.";
        maxJours = max;
    };

    window.showError = function (title, desc) {
        document.getElementById('errTitle').innerText = title;
        document.getElementById('errDesc').innerText = desc;
        document.getElementById('errorModal').style.display = 'flex';
    };

    window.closeModal = function (idModal) { 
        const targetModal = idModal ? document.getElementById(idModal) : document.getElementById('errorModal');
        if (targetModal) targetModal.style.display = 'none'; 
    };

    // Exécution de l'envoi AJAX après validation de la modale
    window.confirmAndSubmit = async function() {
        const form = document.getElementById('congeForm');
        if (!form) return;

        try {
            const res = await fetch('actions/conges/save_conge.php', {
                method: 'POST',
                body: new FormData(form)
            });

            const data = await res.json();

            if (data.status === 'success' || res.ok) {
                window.closeModal('confirmModal');
                
                // Recharge la vue prendre_conge dans index.php sans quitter l'application
                const urlParams = new URLSearchParams(window.location.search);
                const page = urlParams.get('page') || 'prendre_conge';
                window.location.href = `index.php?page=${page}&success=1&jours=` + encodeURIComponent(document.getElementById('jours_input').value);
            } else {
                window.closeModal('confirmModal');
                window.showError("Erreur", data.message || "Impossible d'enregistrer la demande.");
            }
        } catch (e) {
            // Si la réponse PHP est une redirection classique ou du HTML
            window.closeModal('confirmModal');
            window.location.reload();
        }
    };

    // Interception de la soumission pour afficher la modale de confirmation
    document.addEventListener('submit', function (e) {
        if (e.target && e.target.id === 'congeForm') {
            e.preventDefault();
            const jours = parseFloat(document.getElementById('jours_input').value);
            
            if (jours > 15) {
                window.showError("Limite dépassée", "Le nombre de jours maximum par prise est de 15 jours.");
                return false;
            }
            if (jours > maxJours) {
                window.showError("Solde insuffisant", "Vous ne disposez que de " + maxJours + " jours sur cette décision.");
                return false;
            }

            // Injection dynamique du nombre de jours dans la modale
            document.getElementById('confirmJoursText').innerText = jours;
            document.getElementById('confirmModal').style.display = 'flex';
        }
    });
})();