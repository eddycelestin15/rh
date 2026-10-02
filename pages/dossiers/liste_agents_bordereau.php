<?php
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../includes/check_session.php';

    $userIm = $_SESSION['user_im'];
    $stmt = $pdo->prepare("SELECT code_lieu_affectation, niveau, role_specifique FROM utilisateurs WHERE im = ?");
    $stmt->execute([$userIm]);
    $user = $stmt->fetch();
?>

<div class="m-5 p-6 bg-white rounded-2xl shadow-sm border border-slate-200">
    <h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
        <i class="fas fa-users text-blue-500"></i> Liste des agents pour bordereau
    </h2>
    
    <div class="overflow-x-auto">
        <table id="tableAgents" class="w-full text-sm">
            <thead class="bg-blue-600 text-white">
                <tr>
                    <th>N°</th>
                    <th>DREN</th>
                    <th>CISCO</th>
                    <th>ZAP</th>
                    <th>Lieu de service</th>
                    <th>Nom et Prénoms</th>
                    <th>IM</th>
                    <th>Corps et Grade</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                </tbody>
        </table>
    </div>
</div>
<script>
(function() {
    function initAgentTable() {
        const $table = $('#tableAgents');
        if ($table.length === 0) {
            setTimeout(initAgentTable, 50);
            return;
        }

        const params = new URLSearchParams(window.location.search);
        
        $('#tableAgents').DataTable({
            "destroy": true,
            "processing": true,
            "pageLength": 5, 
            "lengthMenu": [5, 10, 25, 50], 
            "dom": '<"flex justify-between items-center mb-4"f>rt<"flex justify-between items-center mt-4"ip>',
            "ajax": {
                "url": "api/bordereaux/api_agents_bordereau.php",
                "type": "POST",
                "data": {
                    "action": "list_agents", 
                    "type_bordereau": params.get('type_bordereau'),
                    "filter_type_dos": params.get('filter_type_dos'),
                    "service_destination": params.get('service_destination')
                }
            },
            "language": { "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/French.json" },
            "columns": [
                { "data": null, "render": (data, type, row, meta) => meta.row + 1 },
                { "data": "dren" },
                { "data": "cisco" },
                { "data": "zap" },
                { "data": "lieu_service" },
                { "data": "nom_complet" },
                { "data": "im" },
                { "data": "corps_grade" },
                { "data": null, "render": (data) => `<button onclick="ajouterAgent('${data.im}')" class="bg-emerald-500 text-white px-3 py-1 rounded-lg text-xs font-bold hover:bg-emerald-600"><i class="fas fa-plus mr-1"></i> Ajouter</button>` }
            ]
        });
    }

    initAgentTable();
})();

function ajouterAgent(im) {
    Swal.fire('Succès', 'Agent ajouté au bordereau', 'success');
}
</script>