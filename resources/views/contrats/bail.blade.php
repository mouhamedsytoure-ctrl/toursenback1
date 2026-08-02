<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>
  @page { margin: 90px 45px 60px 45px; }
  body { font-family: "DejaVu Serif", serif; font-size: 12px; color:#000; line-height:1.5; text-align:justify; }
  .logo { text-align:center; margin-bottom:6px; }
  .logo img { width:300px; }
  .entete { text-align:center; font-weight:bold; }
  h2.titre { text-align:center; text-decoration:underline; font-size:14px; margin:14px 0; }
  h3 { text-decoration:underline; font-size:12.5px; margin:14px 0 4px; }
  p { margin:6px 0; }
  ul { margin:6px 0; padding-left:18px; }
  li { margin:5px 0; }
  .sign { margin-top:50px; width:100%; }
  .sign td { width:50%; vertical-align:top; }
  .b { font-weight:bold; }
</style>
</head>
<body>
  @if(!empty($logo))<div class="logo"><img src="{{ $logo }}" alt="{{ $agence['nom'] ?? '' }}"></div>@endif
  <div class="entete">
    {{ $agence['adresse'] ?? '' }}, {{ $agence['ville'] ?? '' }}, Tel : {{ $agence['telephone'] ?? '' }} e-mail :<br>
    {{ $agence['email'] ?? '' }}
  </div>
  <h2 class="titre">CONTRAT DE LOCATION</h2>

  <p><span class="b">Entre les soussignés :</span></p>
  <p><span class="b">{{ $agence['nom'] ?? '' }}</span> représenté par <span class="b">{{ $agence['representant'] ?? '' }}</span>, ci-après dénommé, le bailleur,</p>
  <p><span class="b">D'une part,</span></p>
  <p><span class="b">ET</span></p>
  <p>{{ $civ }} <span class="b">{{ $nom }}</span>, ci-après dénommé le preneur,</p>
  <p><span class="b">D'autre part,</span></p>

  <p>Il a été arrêté et convenu ce qui suit :</p>
  <p>Le Bailleur <span class="b">{{ $agence['nom'] ?? '' }}</span> donne en location,</p>
  <p>Le Preneur <span class="b">{{ $nom }}</span> qui accepte,</p>
  <p>Les locaux dont la désignation suit :</p>

  <h3>DESIGNATION</h3>
  <p>Dans un Immeuble sis à {{ $villeImm }}, <span class="b">{{ $adresseImm }}</span>, un {{ $typeLog }} situé au <span class="b">{{ $etage }}</span>, à usage <span class="b">{{ ucfirst($usage) }}</span> dont la désignation suit :</p>
  <p><span class="b">{{ $compo }}</span></p>
  <p>Tel que tout se poursuit, s'étend et se comporte sans qu'il en soit besoin d'en établir une description plus détaillée, le preneur déclarant connaître parfaitement le lieu pour l'avoir visité.</p>

  <h3>LOYER :</h3>
  <p>La présente location est acceptée et consentie moyennant un loyer mensuel de <span class="b">{{ $loyerLettres }}</span> ({{ $loyer }} FCFA), payable avant <span class="b">le {{ $jourTxt }}</span> de chaque mois. Le montant du loyer pourra être révisé en fonction de la réglementation en vigueur et celle-ci est accordée à chacune des parties contractantes pendant la durée du bail.</p>

  <h3>CHARGES :</h3>
  <p>Les factures d'eau et d'électricité sont à la charge du preneur, l'entrée étant fixée au <span class="b">{{ $debut }}</span>. Le preneur assurera la charge des entretiens à caractère locatif des locaux et participera à l'entretien de l'Immeuble pendant toute la durée du bail et remettra les locaux tel qu'ils étaient à la fin de l'occupation. Un état des lieux sera dressé contradictoirement avec le preneur lors de la prise de possession des lieux et à la fin du bail.</p>

  <h3>GARANTIE :</h3>
  <p>Une Somme de <span class="b">{{ $cautionLettres }} Francs</span> représentant {{ $moisCaution }} mois de loyers sera versée à titre de caution et d'avance sur loyer. La caution ne sera restituée qu'après remise en état des lieux en parfait état locatif, quelle qu'ait été la durée d'occupation. Un état des lieux contradictoire sera établi à l'entrée et à la fin du bail. À défaut, il sera prélevé sur ladite caution les sommes correspondantes aux frais de remise en état des lieux, ainsi que le montant des factures d'électricité et d'eaux non réglées par le preneur.</p>

  <h3>DUREE DU BAIL :</h3>
  <p>Le bail est consenti pour une durée de {{ $nbMois }} mois prenant effet à la date du <span class="b">{{ $debut }}</span> pour terminer le <span class="b">{{ $fin }}</span>. Il est renouvelable par tacite reproductive, le preneur ayant la faculté de dénoncer à l'expiration de chaque période par l'exploitation d'huissier avec préavis de <span class="b">deux (02) mois</span>. Le bailleur aura la faculté de dénoncer le présent contrat dans les mêmes conditions avec un préavis de <span class="b">six (06) mois</span> et en respectant les termes de la loi 85-37 du 23 Juillet 1985, et l'article 574 du code des obligations civiles et commerciales. Si le preneur veut résilier son contrat avant la fin du bail, il est tenu d'avertir le bailleur <span class="b">02 mois avant</span>, sous peine d'avoir à payer une indemnité égale à un terme de loyer.</p>

  <h3>DÉSIGNATION DES LIEUX LOUÉS :</h3>
  <p>Les lieux sont loués à usage <span class="b">{{ $usage }}</span>.</p>

  <h3>CHARGES ET CONDITIONS :</h3>
  <ul>
    <li>le preneur garnira les lieux et les tiendra constamment pourvus de mobilier et matériel en quantité suffisante pour garantir le paiement des loyers et charges.</li>
    <li>Le preneur entretiendra les lieux en bon état et les restituera de même. Il sera tenu aux réparations locatives (débouchage des canalisations), le bailleur étant tenu aux grosses réparations.</li>
    <li>Le preneur s'interdit d'encombrer les parties communes ou les sorties de secours, s'ils existent.</li>
    <li>Il ne devra rien placer aux fenêtres ou balcons, qui ne puissent représenter un danger pour les passants ou nuire à l'aspect extérieur de l'immeuble. Les climatiseurs devront avoir une plaque de récupération et un tuyau d'évacuation d'eau.</li>
    <li>Le preneur ne pourra faire aucun aménagement, aucune transformation de l'état ou la disposition des locaux, sans l'autorisation préalable, par écrit du bailleur. Tout embellissement, aménagement, amélioration appartiendront de plein droit au bailleur à la fin du bail, à moins que celui-ci ne préfère exiger du preneur qu'il remette les lieux en état.</li>
    <li>Le preneur s'engage à effectuer son aménagement et son déménagement avec le plus grand soin. En cas de détérioration des parties communes, des réparations seraient à sa charge.</li>
    <li>Le preneur est tenu de ne faire aucune manifestation sonore qui pourrait déranger les locataires sans l'autorisation préalable du bailleur.</li>
    <li>Le Preneur est tenu de ne faire aucun dérangement ou bruit qui pourra nuire au calme des locataires sous peine d'une résiliation immédiate du contrat.</li>
  </ul>

  <h3>CLAUSE RESOLUTOIRE</h3>
  <p>En cas de défaillance du preneur, le contrat sera résilié selon les dispositions de l'<span class="b">article 571 du code des Obligations Civiles et Commerciales.</span></p>

  <h3>ENREGISTREMENT :</h3>
  <p>Les frais et horaires des présents, ainsi que les droits de timbre et d'enregistrement sont à la charge exclusive du preneur. Celui-ci fera également le nécessaire pour que le renouvellement des droits d'enregistrement soit réglé en temps utile, afin que le bailleur ne puisse être inquiété à ce sujet.</p>

  <h3>ÉLECTION DE DOMICILE :</h3>
  <p>Pour l'exécution des présentes, les parties font élection de domicile :</p>
  <p><span class="b">{{ strtoupper($villeImm) }}</span> le <span class="b">{{ $debut }}</span></p>

  <table class="sign">
    <tr>
      <td style="text-align:center;"><span class="b">LE BAILLEUR</span><br><br>{{ $agence['nom'] ?? '' }}<br>{{ $agence['representant'] ?? '' }}</td>
      <td style="text-align:center;"><span class="b">LE PRENEUR</span><br><i>Précédé de la mention "lu et approuvé"</i><br><br>{{ $nom }}</td>
    </tr>
  </table>
</body>
</html>