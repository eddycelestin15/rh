<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/check_session.php';
if ($_SESSION['user_type'] !== 'admin') { die("Accès restreint"); }
?>

<div id="modalSuccess" class="hidden fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-md transition-all duration-500">
    <div class="bg-white p-10 rounded-[3.5rem] shadow-2xl text-center max-w-sm w-full mx-4 animate-pop-in border border-white/20">
        <div class="w-24 h-24 bg-gradient-to-tr from-green-400 to-emerald-600 text-white rounded-full flex items-center justify-center mx-auto mb-8 shadow-lg shadow-green-200">
            <i class="fas fa-check text-4xl"></i>
        </div>
        <h3 class="text-3xl font-black text-slate-800 uppercase tracking-tighter mb-2 italic">Compte Créé !</h3>
        <p class="text-slate-500 font-medium mb-8">Le nouveau responsable a été enregistré avec succès dans la base de données.</p>
        
        <button onclick="fermerModaleEtRafraichir()" class="w-full py-5 bg-slate-900 text-white rounded-2xl font-black uppercase text-xs tracking-[0.2em] hover:bg-blue-600 transition-all shadow-xl active:scale-95">
            Continuer <i class="fas fa-arrow-right ml-2"></i>
        </button>
    </div>
</div>

<div class="p-8 max-w-4xl mx-auto animate-fadeIn">

    <form id="formCreerResponsable" class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-white p-10 rounded-[3rem] shadow-2xl shadow-slate-100 border border-slate-50">
        
        <div class="space-y-4">
            <h3 class="text-xs font-black text-blue-600 uppercase tracking-widest mb-4 flex items-center gap-2">
                <span class="w-8 h-[2px] bg-blue-600"></span> Identité du responsable
            </h3>
            <div class="group">
                <label class="text-[10px] font-black text-slate-400 uppercase ml-2 mb-1 block">Nom</label>
                <input type="text" name="nom" placeholder="Ex: LANTOARIMANANA" required class="w-full p-4 bg-slate-50 rounded-2xl border-2 border-transparent focus:border-blue-500 focus:bg-white outline-none transition-all">
            </div>
            <div class="group">
                <label class="text-[10px] font-black text-slate-400 uppercase ml-2 mb-1 block">Prénoms</label>
                <input type="text" name="prenoms" placeholder="Ex: Jean Briand" class="w-full p-4 bg-slate-50 rounded-2xl border-2 border-transparent focus:border-blue-500 focus:bg-white outline-none transition-all">
            </div>
            <div class="group">
                <label class="text-[10px] font-black text-slate-400 uppercase ml-2 mb-1 block">IM</label>
                <input type="text" name="im" placeholder="Ex : 367060" required class="w-full p-4 bg-slate-50 rounded-2xl border-2 border-transparent focus:border-blue-500 focus:bg-white outline-none transition-all font-mono">
            </div>
        </div>

        <div class="space-y-4">
            <h3 class="text-xs font-black text-amber-500 uppercase tracking-widest mb-4 flex items-center gap-2">
                <span class="w-8 h-[2px] bg-amber-500"></span> Privilèges
            </h3>
            <div class="group">
                <label class="text-[10px] font-black text-slate-400 uppercase ml-2 mb-1 block">Niveau</label>
                <select id="selectNiveau" name="niveau" required class="w-full p-4 bg-slate-50 rounded-2xl border-2 border-transparent focus:border-blue-500 outline-none appearance-none cursor-pointer font-bold text-slate-700">
                    <option value="">-- Choisir un niveau --</option>
                    <option value="central">Direction des Ressources Humaines (DRH)</option>
                    <option value="regional">Direction Régionale de l'Education Nationale (DREN)</option>
                    <option value="district">Circonscription Scolaire (CISCO)</option>
                    <option value="crfrp">Centre Régional de Formation et de Recherche Pédagogique (CRFRP)</option>
                </select>
            </div>
            <div class="group">
                <label class="text-[10px] font-black text-slate-400 uppercase ml-2 mb-1 block">Rôle Responsable</label>
                <select id="selectRole" name="role_specifique" required class="w-full p-4 bg-slate-50 rounded-2xl border-2 border-transparent focus:border-blue-500 outline-none appearance-none cursor-pointer font-bold text-slate-700">
                    <option value="">-- Choisir d'abord un niveau --</option>
                </select>
            </div>

            <!-- Masqués par défaut au chargement de la page -->
            <div id="filter_region" class="group hidden">
                <label class="text-[10px] font-black text-slate-400 uppercase ml-2 mb-1 block">Région</label>
                <select name="region_id" id="region_id" class="w-full p-4 bg-blue-50/50 text-blue-700 font-bold rounded-2xl border-2 border-transparent focus:border-blue-500 outline-none">
                    <option value="">-- Choisir une région --</option>
                </select>
            </div>

            <div id="filter_district" class="group hidden">
                <label class="text-[10px] font-black text-slate-400 uppercase ml-2 mb-1 block">District (CISCO)</label>
                <select name="district_id" id="district_id" class="w-full p-4 bg-blue-50/50 text-blue-700 font-bold rounded-2xl border-2 border-transparent focus:border-blue-500 outline-none">
                    <option value="">-- Choisir un district --</option>
                </select>
            </div>

            <div id="filter_crfrp" class="group hidden">
                <label class="text-[10px] font-black text-slate-400 uppercase ml-2 mb-1 block">CRFRP</label>
                <select name="crfrp_id" id="crfrp_id" class="w-full p-4 bg-blue-50/50 text-blue-700 font-bold rounded-2xl border-2 border-transparent focus:border-blue-500 outline-none">
                    <option value="">-- Choisir un CRFRP --</option>
                </select>
            </div>
        </div>

        <div class="md:col-span-2 flex items-center justify-between pt-6 border-t border-slate-50 mt-4">
            <div class="flex items-center gap-2 text-amber-600 bg-amber-50 px-4 py-2 rounded-full">
                <i class="fas fa-info-circle text-xs"></i>
                <span class="text-[10px] font-bold">Le mot de passe par défaut est : 123456</span>
            </div>
            <button type="submit" class="bg-slate-900 text-white px-10 py-4 rounded-2xl font-black uppercase tracking-widest hover:bg-blue-600 hover:scale-105 transition-all shadow-xl shadow-slate-200">
                Valider la création <i class="fas fa-arrow-right ml-2"></i>
            </button>
        </div>
    </form>
</div>

<style>
@keyframes pop-in {
    0% { transform: scale(0.8); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}
.animate-pop-in { animation: pop-in 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); }
</style>
<script src="assets/js/create_responsable.js?v=1.1"></script>