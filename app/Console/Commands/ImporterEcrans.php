<?php

namespace App\Console\Commands;

use App\Models\Produit;
use Illuminate\Console\Command;
use ZipArchive;

/**
 * Importe l'inventaire des écrans depuis un fichier Excel (.xlsx).
 * Colonnes attendues : Modele, Marque, prix min, Quantite, prix max, categorie.
 *
 * Choix de correspondance (modifiable) :
 *   - prix de vente (prix_unitaire) = "prix max"
 *   - prix d'achat (prix_achat)     = "prix min"
 *   - stock                          = "Quantite"
 *   - catégorie                      = "Écran de téléphone"
 *   - produit activé                 = oui
 *
 * Usage : php artisan import:ecrans "chemin/vers/ecran.xlsx"
 */
class ImporterEcrans extends Command
{
    protected $signature = 'import:ecrans {fichier} {--categorie=Écran de téléphone} {--prefixe=Écran }';
    protected $description = 'Importe des produits (modèle, prix, stock) depuis un fichier Excel';

    public function handle(): int
    {
        $fichier = $this->argument('fichier');
        if (! is_file($fichier)) {
            $this->error("Fichier introuvable : {$fichier}");
            return self::FAILURE;
        }

        $lignes = $this->lireXlsx($fichier);
        if (empty($lignes)) {
            $this->error('Aucune donnée lue dans le fichier.');
            return self::FAILURE;
        }

        // Première ligne = en-têtes → repère les colonnes par leur nom.
        $entetes = array_map(fn ($h) => mb_strtolower(trim($h)), array_shift($lignes));
        $col = fn (array $noms) => $this->trouverColonne($entetes, $noms);

        $cModele = $col(['modele', 'modèle', 'petit ecran', 'petit écran', 'nom', 'article', 'designation', 'désignation']);
        $cMin = $col(['prix min', 'prix minimum', 'prix achat', "prix d'achat"]);
        $cQte = $col(['quantite', 'quantité', 'quanrite', 'qte', 'stock']);
        $cMax = $col(['prix max', 'prix maximum', 'prix vente', 'prix de vente', 'prix']);

        if ($cModele === null) {
            $this->error('Colonne "Modele" (ou équivalent) introuvable dans le fichier.');
            return self::FAILURE;
        }

        $categorie = $this->option('categorie');
        $prefixe = $this->option('prefixe');
        $crees = 0; $maj = 0; $ignores = 0;

        foreach ($lignes as $l) {
            $modele = trim($l[$cModele] ?? '');
            if ($modele === '') { $ignores++; continue; }

            $prixVente = $cMax !== null ? $this->entier($l[$cMax] ?? 0) : 0;
            $prixAchat = $cMin !== null ? $this->entier($l[$cMin] ?? 0) : 0;
            $stock = $cQte !== null ? $this->entier($l[$cQte] ?? 0) : 0;

            $produit = Produit::updateOrCreate(
                ['categorie' => $categorie, 'modele' => $modele],
                [
                    'nom' => trim($prefixe.$modele),
                    'prix_unitaire' => $prixVente,
                    'prix_achat' => $prixAchat,
                    'stock' => $stock,
                    'actif' => true,
                ],
            );

            $produit->wasRecentlyCreated ? $crees++ : $maj++;
        }

        $this->info("Import terminé : {$crees} créé(s), {$maj} mis à jour, {$ignores} ignoré(s).");
        return self::SUCCESS;
    }

    private function trouverColonne(array $entetes, array $noms): ?int
    {
        foreach ($noms as $n) {
            $i = array_search(mb_strtolower($n), $entetes, true);
            if ($i !== false) return $i;
        }
        return null;
    }

    private function entier($v): int
    {
        return (int) round((float) preg_replace('/[^0-9.\-]/', '', (string) $v));
    }

    /**
     * Lit la première feuille d'un .xlsx en tableau de lignes (sans dépendance).
     *
     * @return array<int, array<int, string>>
     */
    private function lireXlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return [];

        $shared = [];
        if (($s = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $xml = simplexml_load_string($s);
            foreach ($xml->si as $si) {
                $txt = isset($si->t) ? (string) $si->t : '';
                foreach ($si->r as $r) { $txt .= (string) $r->t; }
                $shared[] = $txt;
            }
        }

        $data = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($data === false) return [];

        $sx = simplexml_load_string($data);
        $lignes = [];
        foreach ($sx->sheetData->row as $row) {
            $cells = []; $maxCol = 0;
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                preg_match('/^[A-Z]+/', $ref, $m);
                $letters = $m[0] ?? 'A'; $idx = 0;
                foreach (str_split($letters) as $ch) { $idx = $idx * 26 + (ord($ch) - 64); }
                $idx--;
                $type = (string) $c['t'];
                $val = $type === 's' ? ($shared[(int) $c->v] ?? '')
                    : ($type === 'inlineStr' ? (string) $c->is->t : (string) $c->v);
                $cells[$idx] = $val;
                if ($idx > $maxCol) $maxCol = $idx;
            }
            $ligne = [];
            for ($i = 0; $i <= $maxCol; $i++) { $ligne[] = $cells[$i] ?? ''; }
            $lignes[] = $ligne;
        }
        return $lignes;
    }
}
