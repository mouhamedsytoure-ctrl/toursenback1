<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>
  @page { margin: 36px 40px; }
  body { font-family: "DejaVu Sans", sans-serif; font-size: 12px; color:#000; }
  .logo { text-align:center; } .logo img { width:300px; }
  .entete { text-align:center; font-size:11px; margin:6px 0 18px; }

  /* bloc destinataire qui "chevauche" le cadre */
  .destwrap { position:relative; height:14px; }
  .dest {
    position:absolute; left:50%; top:6px; width:340px; margin-left:-170px;
    border:1px solid #000; background:#fff; padding:6px 10px; z-index:2;
  }
  .dest .t { text-align:center; font-weight:bold; font-size:11px; }
  .dest .n { font-weight:bold; }
  .dest .a { font-size:11px; }

  .cadre { border:1px solid #000; margin-top:52px; }
  .titre { text-align:center; font-weight:bold; padding-top:40px; }
  .mois { text-align:center; font-weight:bold; }
  .num { text-align:right; font-weight:bold; padding:0 12px 6px; }

  table.q { width:100%; border-collapse:collapse; border-top:1px solid #000; }
  table.q td { vertical-align:top; padding:10px 12px; width:50%; }
  table.q td.left { border-right:1px solid #000; }
  .li { margin:4px 0; }
  .det-t { font-weight:bold; }
  .sign { margin-top:30px; }

  .legal {
    border-top:1px solid #000; padding:8px 12px; font-size:8.5px; text-align:justify; color:#000;
  }
</style>
</head>
<body>
  @if(!empty($logo))<div class="logo"><img src="{{ $logo }}" alt="{{ $agence['nom'] ?? '' }}"></div>@endif
  <div class="entete">{{ $agence['adresse'] ?? '' }}, {{ $agence['ville'] ?? '' }} : {{ $agence['telephone'] ?? '' }}</div>

  <div class="destwrap">
    <div class="dest">
      <div class="t">LOCATAIRE DESTINATAIRE</div>
      <div class="n">{{ $nom }}</div>
      <div class="a">{{ $adresse }}</div>
    </div>
  </div>

  <div class="cadre">
    <div class="titre">Quittance de loyer</div>
    <div class="mois">{{ $mois }}</div>
    <div class="num">Quittance n° {{ $numero }}</div>

    <table class="q">
      <tr>
        <td class="left">
          <div class="li">Recu de : {{ $nom }}</div>
          <div class="li">La somme totale de : {{ $montant }} FCFA</div>
          <div class="li">; le {{ $date }} pour loyer et accessoires des locaux sis a : {{ $adresse }} ;</div>
          <div class="li">Fait a {{ $ville }} le {{ $date }}</div>
          <div class="sign">Signature du bailleur</div>
        </td>
        <td>
          <div class="det-t">Detail</div>
          <div class="li">- Loyer nu : {{ $montant }} FCFA</div>
          <div class="li">- Charges / Provisions de charges :</div>
          <div class="li" style="margin-top:14px">Montant total du terme : {{ $montant }} FCFA</div>
          <div class="li">- Paiement locataire : {{ $montant }} FCFA</div>
          <div class="li">- Solde a payer : 0 FCFA</div>
        </td>
      </tr>
    </table>

    <div class="legal">
      Le paiement de la presente n'emporte pas presomption de paiement des termes anterieurs. Cette quittance ou ce recu annule tous les recus qui auraient pu etre donnes pour acompte verse sur le present terme. En cas de conge precedemment donne, cette quittance ou ce recu representerait l'indemnite d'occupation et ne saurait etre considere comme un titre d'occupation. Sous reserve d'encaissement.
    </div>
  </div>
</body>
</html>
