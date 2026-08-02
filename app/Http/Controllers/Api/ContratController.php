<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contrat;
use App\Models\Logement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class ContratController extends Controller
{
    private function peutGerer(Request $request, string $action): bool
    {
        return $request->user()->hasPermission('contrats', $action)
            || $request->user()->hasPermission('locataires', $action);
    }

    // GET /api/contrats?archive=1
    public function index(Request $request)
    {
        abort_unless($this->peutGerer($request, 'view') || $request->user()->isSuperAdmin(), 403);

        $query = Contrat::with('locataire', 'logement.immeuble');
        if ($request->boolean('archive')) {
            $query->whereNotNull('archived_at');
        } else {
            $query->whereNull('archived_at');
        }
        return response()->json($query->latest()->get());
    }

    // GET /api/contrats/{contrat}
    public function show(Contrat $contrat)
    {
        $contrat->load('locataire', 'logement.immeuble', 'paiements', 'medias');
        return response()->json($contrat);
    }

    /**
     * POST /api/contrats
     * Cree le COMPTE locataire + le contrat DETAILLE (fige).
     */
    public function store(Request $request)
    {
        abort_unless($this->peutGerer($request, 'create'), 403);

        $data = $request->validate([
            'preneur_nom'            => ['required', 'string', 'max:255'],
            'preneur_prenom'         => ['nullable', 'string', 'max:255'],
            'preneur_civilite'       => ['nullable', 'string', 'max:50'],
            'preneur_telephone'      => ['nullable', 'string', 'max:255'],
            'preneur_email'          => ['required', 'email', 'unique:users,email'],
            'preneur_adresse'        => ['nullable', 'string', 'max:255'],
            'preneur_profession'     => ['nullable', 'string', 'max:255'],
            'preneur_nationalite'    => ['nullable', 'string', 'max:255'],
            'preneur_date_naissance' => ['nullable', 'date'],
            'preneur_lieu_naissance' => ['nullable', 'string', 'max:255'],
            'preneur_piece_type'     => ['nullable', 'in:cni,passeport,permis,autre'],
            'preneur_piece_numero'   => ['nullable', 'string', 'max:255'],
            'composition'            => ['nullable', 'string', 'max:255'],
            'usage'                  => ['nullable', 'string', 'max:100'],
            'password'               => ['nullable', 'string', 'min:6'],
            'logement_id'            => ['required', 'exists:logements,id'],
            'date_debut'             => ['required', 'date'],
            'date_fin'               => ['required', 'date', 'after:date_debut'],
            'montant_loyer'          => ['required', 'numeric', 'min:0'],
            'caution'                => ['nullable', 'numeric', 'min:0'],
            'jour_echeance'          => ['nullable', 'integer', 'between:1,31'],
        ]);

        $genere = ! $request->filled('password');
        $plain  = $genere ? Str::random(8) : $data['password'];
        $nomComplet = trim(($data['preneur_prenom'] ?? '') . ' ' . $data['preneur_nom']);

        $res = DB::transaction(function () use ($data, $plain, $nomComplet, $request) {
            $user = User::create([
                'name'      => $nomComplet,
                'email'     => $data['preneur_email'],
                'telephone' => $data['preneur_telephone'] ?? null,
                'password'  => Hash::make($plain),
                'role'      => 'locataire',
                'is_active' => true,
                'agence_id' => $request->user()->agence_id,
            ]);

            $contrat = Contrat::create([
                'logement_id'            => $data['logement_id'],
                'user_id'                => $user->id,
                'preneur_nom'            => $data['preneur_nom'],
                'preneur_prenom'         => $data['preneur_prenom'] ?? null,
                'preneur_civilite'       => $data['preneur_civilite'] ?? null,
                'preneur_telephone'      => $data['preneur_telephone'] ?? null,
                'preneur_email'          => $data['preneur_email'],
                'preneur_adresse'        => $data['preneur_adresse'] ?? null,
                'preneur_profession'     => $data['preneur_profession'] ?? null,
                'preneur_nationalite'    => $data['preneur_nationalite'] ?? null,
                'preneur_date_naissance' => $data['preneur_date_naissance'] ?? null,
                'preneur_lieu_naissance' => $data['preneur_lieu_naissance'] ?? null,
                'preneur_piece_type'     => $data['preneur_piece_type'] ?? null,
                'preneur_piece_numero'   => $data['preneur_piece_numero'] ?? null,
                'composition'            => $data['composition'] ?? null,
                'usage'                  => $data['usage'] ?? null,
                'date_debut'             => $data['date_debut'],
                'date_fin'               => $data['date_fin'],
                'montant_loyer'          => $data['montant_loyer'],
                'caution'                => $data['caution'] ?? 0,
                'jour_echeance'          => $data['jour_echeance'] ?? 5,
                'statut'                 => 'actif',
            ]);

            Logement::where('id', $data['logement_id'])->update(['statut' => 'loue']);
            return [$user, $contrat];
        });

        return response()->json([
            'contrat'      => $res[1]->load('logement.immeuble'),
            'mot_de_passe' => $genere ? $plain : null,
        ], 201);
    }

    // GET /api/contrats/{contrat}/texte  -> VRAI contrat Toursen, rempli automatiquement
    public function texte(Contrat $contrat)
    {
        $contrat->load('logement.immeuble');
        $l  = $contrat->logement;
        $im = $l?->immeuble;

        // Identite de l'agence proprietaire du contrat : chaque agence edite
        // ses baux a son propre nom, jamais a celui d'une autre.
        $ag = $contrat->agence ?: request()->user()?->agence;
        $A  = $ag ? $ag->entete() : [
            'nom' => '__________', 'representant' => '__________', 'fonction' => 'Gerant',
            'adresse' => '__________', 'ville' => 'Dakar',
            'telephone' => '__________', 'email' => '__________',
        ];

        $civ = $contrat->preneur_civilite ?: 'Monsieur/Madame';
        $nom = trim(($contrat->preneur_prenom ?? '') . ' ' . ($contrat->preneur_nom ?? ''));
        if ($nom === '') $nom = '__________';

        $adresseImm = $im?->adresse ?: ($im?->nom ?? '__________');
        $villeImm   = $im?->ville ?: 'Dakar';
        $etage      = $this->labelEtage($l?->etage ?? 0);
        $typeLog    = $l?->type ? str_replace('_', ' ', $l->type) : 'logement';
        $usage      = $contrat->usage ?: 'domestique';
        $compo      = $contrat->composition ?: '__________';

        $loyer   = (int) round($contrat->montant_loyer);
        $caution = (int) round($contrat->caution);
        $loyerLettres   = $this->enLettres($loyer);
        $cautionLettres = $this->enLettres($caution);
        $moisCaution    = $loyer > 0 ? max(1, (int) round($caution / $loyer)) : 2;

        $jour  = (int) ($contrat->jour_echeance ?? 5);
        $jourTxt = str_pad((string) $jour, 2, '0', STR_PAD_LEFT);
        $debut = $contrat->date_debut?->translatedFormat('d F Y') ?? '__________';
        $fin   = $contrat->date_fin?->translatedFormat('d F Y') ?? '__________';
        $nbMois = ($contrat->date_debut && $contrat->date_fin)
            ? max(1, $contrat->date_debut->diffInMonths($contrat->date_fin)) : 12;

        $texte = <<<TXT
{$A['nom']}
{$A['adresse']}
Tel : {$A['telephone']}   -   Email : {$A['email']}

CONTRAT DE LOCATION

ENTRE LES SOUSSIGNES :

{$A['nom']}, represente par {$A['representant']}, ci-apres denomme le bailleur,
D'une part,

ET

{$civ} {$nom}, ci-apres denomme le preneur,
D'autre part,

Il a ete arrete et convenu ce qui suit :
Le Bailleur {$A['nom']} donne en location,
Le Preneur {$nom} qui accepte,
Les locaux dont la designation suit :

DESIGNATION
Dans un Immeuble sis a {$villeImm}, {$adresseImm}, un {$typeLog} situe au {$etage},
a usage {$usage}, dont la designation suit :
{$compo}.
Tel que tout se poursuit, s'etend et se comporte sans qu'il en soit besoin d'en etablir une
description plus detaillee, le preneur declarant connaitre parfaitement le lieu pour l'avoir visite.

LOYER
La presente location est acceptee et consentie moyennant un loyer mensuel de
{$loyerLettres} ({$loyer} FCFA), payable avant le {$jourTxt} de chaque mois.
Le montant du loyer pourra etre revise en fonction de la reglementation en vigueur.

CHARGES
Les factures d'eau et d'electricite sont a la charge du preneur, l'entree etant fixee au {$debut}.
Le preneur assurera la charge des entretiens a caractere locatif des locaux et participera a
l'entretien de l'Immeuble pendant toute la duree du bail et remettra les locaux tel qu'ils etaient
a la fin de l'occupation. Un etat des lieux sera dresse contradictoirement avec le preneur lors de
la prise de possession des lieux et a la fin du bail.

GARANTIE
Une somme de {$cautionLettres} ({$caution} FCFA) representant {$moisCaution} mois de loyer sera
versee a titre de caution et d'avance sur loyer. La caution ne sera restituee qu'apres remise en
etat des lieux en parfait etat locatif, quelle qu'ait ete la duree d'occupation. A defaut, il sera
preleve sur ladite caution les sommes correspondantes aux frais de remise en etat des lieux, ainsi
que le montant des factures d'electricite et d'eau non reglees par le preneur.

DUREE DU BAIL
Le bail est consenti pour une duree de {$nbMois} mois prenant effet a la date du {$debut} pour se
terminer le {$fin}. Il est renouvelable par tacite reconduction, le preneur ayant la faculte de
denoncer a l'expiration de chaque periode par exploit d'huissier avec un preavis de deux (02) mois.
Le bailleur aura la faculte de denoncer le present contrat dans les memes conditions avec un preavis
de six (06) mois, en respectant les termes de la loi 85-37 du 23 Juillet 1985 et l'article 574 du
code des obligations civiles et commerciales.
Si le preneur veut resilier son contrat avant la fin du bail, il est tenu d'avertir le bailleur
02 mois avant, sous peine d'avoir a payer une indemnite egale a un terme de loyer.

DESIGNATION DES LIEUX LOUES
Les lieux sont loues a usage {$usage}.

CHARGES ET CONDITIONS
- Le preneur garnira les lieux et les tiendra constamment pourvus de mobilier et materiel en quantite
  suffisante pour garantir le paiement des loyers et charges.
- Le preneur entretiendra les lieux en bon etat et les restituera de meme. Il sera tenu aux reparations
  locatives, le bailleur etant tenu aux grosses reparations.
- Le preneur s'interdit d'encombrer les parties communes ou les sorties de secours.
- Il ne devra rien placer aux fenetres ou balcons qui puisse representer un danger pour les passants
  ou nuire a l'aspect exterieur de l'immeuble. Les climatiseurs devront avoir une plaque de
  recuperation et un tuyau d'evacuation d'eau.
- Le preneur ne pourra faire aucun amenagement ni transformation des locaux sans l'autorisation
  prealable et ecrite du bailleur. Tout embellissement ou amelioration appartiendra de plein droit
  au bailleur a la fin du bail, sauf si celui-ci prefere exiger la remise en etat.
- Le preneur ne pourra sous-louer tout ou partie des locaux ni changer leur destination sans l'accord
  ecrit du bailleur et un avenant au present bail.
- Le preneur satisfera a toutes les prescriptions des services de police, de voirie et d'hygiene.
- Le preneur acquittera la taxe d'enlevement des ordures menageres et ses consommations d'eau et
  d'electricite.
- Le preneur s'engage a assurer contre l'incendie son mobilier et les risques locatifs aupres d'une
  compagnie d'assurance accreditee au Senegal.
- Le preneur devra prendre toutes les precautions pour le gardiennage des lieux, le bailleur n'etant
  pas responsable des vols pouvant y survenir.
- Le preneur ne doit pas depasser le nombre de personnes declare, sous peine de resiliation immediate.
- Le preneur s'engage a effectuer son amenagement et son demenagement avec le plus grand soin.
- Le preneur est tenu de ne faire aucun bruit pouvant nuire au calme des autres locataires, sous peine
  de resiliation immediate du contrat.

CLAUSE RESOLUTOIRE
En cas de defaillance du preneur, le contrat sera resilie selon les dispositions de l'article 571 du
code des Obligations Civiles et Commerciales.

ENREGISTREMENT
Les frais des presentes, ainsi que les droits de timbre et d'enregistrement, sont a la charge
exclusive du preneur.

ELECTION DE DOMICILE
Pour l'execution des presentes, les parties font election de domicile aux adresses indiquees.

Fait a {$A['ville']}, le {$debut}.
(Precede de la mention "lu et approuve")


LE BAILLEUR                                                  LE PRENEUR
{$A['nom']}                                          {$nom}
{$A['representant']}
TXT;

        return response()->json(['contrat_id' => $contrat->id, 'texte' => $texte]);
    }

    // GET /api/contrats/{contrat}/pdf  -> le VRAI contrat en PDF (logo + mise en page identique)
    public function pdf(Contrat $contrat)
    {
        $contrat->load('logement.immeuble');
        $l = $contrat->logement;
        $im = $l?->immeuble;

        $nom = trim(($contrat->preneur_prenom ?? '') . ' ' . ($contrat->preneur_nom ?? ''));
        if ($nom === '') $nom = '__________';
        $loyer   = (int) round($contrat->montant_loyer);
        $caution = (int) round($contrat->caution);

        $ag = $contrat->agence ?: request()->user()?->agence;
        $A  = $ag ? $ag->entete() : [];

        $data = [
            'agence'         => $A,
            'logo'           => $A['logo'] ?? null,
            'civ'            => $contrat->preneur_civilite ?: 'Monsieur/Madame',
            'nom'            => $nom,
            'villeImm'       => $im?->ville ?: 'Dakar',
            'adresseImm'     => $im?->adresse ?: ($im?->nom ?? '__________'),
            'etage'          => $this->labelEtage($l?->etage ?? 0),
            'typeLog'        => $l?->type ? str_replace('_', ' ', $l->type) : 'logement',
            'usage'          => $contrat->usage ?: 'domestique',
            'compo'          => $contrat->composition ?: '__________',
            'loyer'          => $loyer,
            'loyerLettres'   => $this->enLettres($loyer),
            'jourTxt'        => str_pad((string) (int) ($contrat->jour_echeance ?? 5), 2, '0', STR_PAD_LEFT),
            'debut'          => $contrat->date_debut?->locale('fr')->translatedFormat('d F Y') ?? '__________',
            'fin'            => $contrat->date_fin?->locale('fr')->translatedFormat('d F Y') ?? '__________',
            'caution'        => $caution,
            'cautionLettres' => $this->enLettres($caution),
            'moisCaution'    => $loyer > 0 ? max(1, (int) round($caution / $loyer)) : 2,
            'nbMois'         => ($contrat->date_debut && $contrat->date_fin) ? max(1, $contrat->date_debut->diffInMonths($contrat->date_fin)) : 12,
        ];

        return Pdf::loadView('contrats.bail', $data)->setPaper('a4')->stream('contrat_' . $contrat->id . '.pdf');
    }

    // PUT /api/contrats/{contrat}/resilier
    public function resilier(Request $request, Contrat $contrat)
    {
        abort_unless($this->peutGerer($request, 'update'), 403);
        $data = $request->validate(['motif_fin' => ['nullable', 'string', 'max:255']]);

        $contrat->update([
            'statut'    => 'resilie',
            'date_fin'  => now(),
            'motif_fin' => $data['motif_fin'] ?? 'Resiliation',
        ]);
        Logement::where('id', $contrat->logement_id)->update(['statut' => 'disponible']);

        return response()->json($contrat->fresh());
    }

    // PUT /api/contrats/{contrat}/bloquer
    public function bloquer(Request $request, Contrat $contrat)
    {
        abort_unless($this->peutGerer($request, 'update'), 403);
        $contrat->update(['est_bloque' => true]);
        User::where('id', $contrat->user_id)->update(['is_active' => false]);
        return response()->json($contrat->fresh());
    }

    // PUT /api/contrats/{contrat}/archiver
    public function archiver(Request $request, Contrat $contrat)
    {
        abort_unless($this->peutGerer($request, 'update'), 403);
        $contrat->update(['archived_at' => now()]);
        return response()->json($contrat->fresh());
    }

    // ---------- Helpers ----------
    private function labelEtage(int $e): string
    {
        if ($e === 0) return 'rez-de-chaussee';
        if ($e === 1) return '1er etage';
        return $e . 'e etage';
    }

    // Nombre entier -> lettres (francais), pour les montants
    private function enLettres(int $n): string
    {
        if ($n === 0) return 'zero';
        $unites = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
            'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
        $dizaines = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt'];

        $convertirCent = function (int $c) use (&$convertirCent, $unites, $dizaines): string {
            if ($c < 20) return $unites[$c];
            if ($c < 100) {
                $d = intdiv($c, 10); $u = $c % 10;
                if ($d === 7 || $d === 9) {
                    $base = $dizaines[$d];
                    $reste = 10 + $u;
                    return trim($base . '-' . $unites[$reste]);
                }
                $mot = $dizaines[$d];
                if ($u === 1 && $d !== 8) return $mot . ' et un';
                if ($u === 0) return ($d === 8) ? $mot . 's' : $mot;
                return $mot . '-' . $unites[$u];
            }
            $cent = intdiv($c, 100); $reste = $c % 100;
            $prefix = ($cent === 1) ? 'cent' : $unites[$cent] . ' cent';
            if ($reste === 0) return ($cent > 1) ? $prefix . 's' : $prefix;
            return $prefix . ' ' . $convertirCent($reste);
        };

        $parts = [];
        $millions = intdiv($n, 1000000); $n %= 1000000;
        $milliers = intdiv($n, 1000);     $n %= 1000;
        $reste    = $n;

        if ($millions > 0) {
            $parts[] = ($millions === 1 ? 'un million' : $convertirCent($millions) . ' millions');
        }
        if ($milliers > 0) {
            $parts[] = ($milliers === 1 ? 'mille' : $convertirCent($milliers) . ' mille');
        }
        if ($reste > 0) {
            $parts[] = $convertirCent($reste);
        }
        return ucfirst(implode(' ', $parts));
    }
}