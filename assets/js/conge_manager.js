function selectYear(year) {
    document.querySelectorAll('.year-card').forEach(c => c.classList.remove('active'));
    document.getElementById('card-' + year).classList.add('active');
}

// Ajout du paramètre 'im' ici
function doPrint(event, year, im) {
    event.stopPropagation();
    const card = document.getElementById('card-' + year);
    card.classList.add('printed');
    
    // On utilise la variable 'im' passée en argument
    const downloadUrl = `generate_conge.php?im=${im}&years=${year}&mode=decision`;
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = downloadUrl;
    document.body.appendChild(iframe);

    setTimeout(() => {
        document.body.removeChild(iframe);
        if(typeof loadPage === 'function') {
            loadPage('pages/conges/demande_conge.php', 'Demande congé');
        }
    }, 2000);
}

// Ajout des paramètres 'im' et 'allYears' ici
function printAllPieces(im, allYears) {
    if(allYears.length > 0) {
        const downloadUrl = `generate_conge.php?im=${im}&years=${allYears.join(',')}&mode=pieces`;
        
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = downloadUrl;
        document.body.appendChild(iframe);

        setTimeout(() => {
            document.body.removeChild(iframe);
            if(typeof loadPage === 'function') {
                loadPage('pages/conges/demande_conge.php', 'Demande congé');
            }
        }, 3000); 
    }
}