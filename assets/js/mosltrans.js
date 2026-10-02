/*
 * MOSLTRANS — comportements du site vitrine.
 * Menu mobile, recherche, animations, diaporama, fenêtres de services,
 * suivi d'envoi, carrousel de témoignages et préférences cookies.
 */
(function () {

  // ---------- compteur de caractères du message ----------
  var champMessage = document.querySelector('#lead_message') || document.querySelector('textarea[name$="[message]"]');
  var compteurTexte = document.getElementById('ct-counter');
  if (champMessage && compteurTexte) {
    var majCompteur = function () {
      compteurTexte.textContent = champMessage.value.length + ' / 255 caractères';
    };
    champMessage.addEventListener('input', majCompteur);
    majCompteur();
  }

  // ---------- demande de devis (maquette : rien n'est envoyé ni enregistré) ----------
  var devisForm = document.getElementById('devis-form');
  if (devisForm) {
    var devisOk = document.getElementById('dv-ok');
    var reglesDevis = {
      'dv-type': function (v) { return v ? '' : 'Indiquez le type de marchandise.'; },
      'dv-adr-d': function (v) { return v ? '' : 'Indiquez l’adresse de départ.'; },
      'dv-cp-d': function (v) { return /^\d{5}$/.test(v) ? '' : 'Code postal à 5 chiffres.'; },
      'dv-ville-d': function (v) { return v ? '' : 'Indiquez la ville de départ.'; },
      'dv-adr-a': function (v) { return v ? '' : 'Indiquez l’adresse de destination.'; },
      'dv-cp-a': function (v) { return /^\d{5}$/.test(v) ? '' : 'Code postal à 5 chiffres.'; },
      'dv-ville-a': function (v) { return v ? '' : 'Indiquez la ville de destination.'; },
      'dv-nom': function (v) { return v ? '' : 'Indiquez votre nom ou celui de l’entreprise.'; },
      'dv-mail': function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? '' : 'Adresse e-mail invalide.'; }
    };

    devisForm.addEventListener('submit', function (event) {
      event.preventDefault();
      var premierChampFaux = null;
      Object.keys(reglesDevis).forEach(function (id) {
        var champ = document.getElementById(id);
        var zoneErreur = document.getElementById(id + '-error');
        if (!champ || !zoneErreur) return;
        var message = reglesDevis[id](champ.value.trim());
        zoneErreur.textContent = message;
        champ.setAttribute('aria-invalid', message ? 'true' : 'false');
        if (message && !premierChampFaux) premierChampFaux = champ;
      });

      if (premierChampFaux) { devisOk.classList.remove('show'); premierChampFaux.focus(); return; }

      devisForm.reset();
      devisOk.classList.add('show');
      devisOk.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  }

  // ---------- préférences cookies ----------
  var ck = document.getElementById('ck');
  var toast = document.getElementById('toast');
  var CLE = 'mosltrans-cookies';

  function ckToast(texte) {
    document.getElementById('toast-texte').textContent = texte;
    toast.classList.add('show');
    setTimeout(function () { toast.classList.remove('show'); }, 3500);
  }
  function ckDejaFait() { try { return localStorage.getItem(CLE) !== null; } catch (e) { return false; } }
  function ckEnregistrer(v) { try { localStorage.setItem(CLE, JSON.stringify(v)); } catch (e) {} }
  function ckOuvrir() {
    var choix = {};
    try { choix = JSON.parse(localStorage.getItem(CLE)) || {}; } catch (e) {}
    ck.querySelectorAll('.sw[data-cat]').forEach(function (sw) {
      sw.setAttribute('aria-checked', choix[sw.getAttribute('data-cat')] ? 'true' : 'false');
    });
    ck.classList.add('open');
  }

  if (!ckDejaFait()) { setTimeout(function () { ck.classList.add('open'); }, 700); }

  ck.querySelectorAll('.sw[data-cat]').forEach(function (sw) {
    sw.addEventListener('click', function () {
      sw.setAttribute('aria-checked', sw.getAttribute('aria-checked') === 'true' ? 'false' : 'true');
    });
  });

  ck.querySelectorAll('[data-choix]').forEach(function (bouton) {
    bouton.addEventListener('click', function () {
      var choix = bouton.getAttribute('data-choix');
      var reglages = { essentiel: true, fonctionnel: false, audience: false, marketing: false };

      if (choix === 'tout') {
        reglages.fonctionnel = reglages.audience = reglages.marketing = true;
        ck.querySelectorAll('.sw[data-cat]').forEach(function (sw) { sw.setAttribute('aria-checked', 'true'); });
      } else if (choix === 'reglages') {
        ck.querySelectorAll('.sw[data-cat]').forEach(function (sw) {
          reglages[sw.getAttribute('data-cat')] = sw.getAttribute('aria-checked') === 'true';
        });
      }

      ckEnregistrer(reglages);
      ck.classList.remove('open');

      if (choix === 'refus') ckToast('Seuls les composants essentiels sont activés.');
      else if (choix === 'tout') ckToast('Merci, tous les composants sont activés.');
      else ckToast('Vos préférences ont été enregistrées.');
    });
  });

  ck.addEventListener('click', function (e) { if (e.target.hasAttribute('data-ck-close')) ck.classList.remove('open'); });
  document.getElementById('ck-lien').addEventListener('click', function () { ck.classList.remove('open'); });

  var lienCookies = document.getElementById('lien-cookies');
  if (lienCookies) {
    lienCookies.addEventListener('click', function (event) { event.preventDefault(); ckOuvrir(); });
  }

  // ---------- menu burger ----------
  var burger = document.getElementById('burger');
  var panneau = document.getElementById('panneau');
  var voileMenu = document.getElementById('voile-menu');
  var liensPanneau = panneau.querySelectorAll('nav a, .bas a');

  // le lien surligné correspond à la page affichée (le template surligne toujours « Accueil »)
  panneau.querySelectorAll('nav a').forEach(function (a) {
    a.classList.toggle('actif', a.pathname === window.location.pathname && !a.hash);
  });

  function ouvrirMenu() {
    document.body.classList.add('menu-ouvert');
    burger.setAttribute('aria-expanded', 'true');
    burger.setAttribute('aria-label', 'Fermer le menu');
  }
  function fermerMenu() {
    document.body.classList.remove('menu-ouvert');
    burger.setAttribute('aria-expanded', 'false');
    burger.setAttribute('aria-label', 'Ouvrir le menu');
  }

  burger.addEventListener('click', function () {
    document.body.classList.contains('menu-ouvert') ? fermerMenu() : ouvrirMenu();
  });
  document.getElementById('fermer-menu').addEventListener('click', fermerMenu);
  voileMenu.addEventListener('click', fermerMenu);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') fermerMenu(); });

  liensPanneau.forEach(function (lien) {
    lien.addEventListener('click', function () {
      panneau.querySelectorAll('nav a').forEach(function (a) { a.classList.remove('actif'); });
      if (lien.parentNode.tagName === 'NAV') lien.classList.add('actif');
      fermerMenu();
    });
  });

  // ---------- recherche ----------
  var btnSearch = document.getElementById('btn-search');
  var search = document.getElementById('search');
  btnSearch.addEventListener('click', function () {
    var ouvert = search.classList.toggle('open');
    btnSearch.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
    if (ouvert) { var champ = search.querySelector('input'); if (champ) champ.focus(); }
  });

  // la recherche oriente vers la page ou la section qui correspond au mot tapé
  var formRecherche = search.querySelector('form');
  var champRecherche = search.querySelector('input');
  var messageRecherche = document.createElement('p');
  messageRecherche.className = 'search-msg';
  messageRecherche.setAttribute('role', 'status');
  formRecherche.parentNode.appendChild(messageRecherche);

  function lienVers(fin) {
    var a = document.querySelector('a[href$="' + fin + '"]');
    return a ? a.getAttribute('href') : null;
  }
  var logoAccueil = document.querySelector('header a.brand');
  var accueil = logoAccueil ? logoAccueil.getAttribute('href') : '/';
  var DESTINATIONS = [
    { mots: ['devis', 'tarif', 'prix', 'cout', 'estimation', 'cotation'], lien: lienVers('/devis') },
    { mots: ['suivi', 'suivre', 'colis', 'commande', 'numero', 'livraison', 'envoi', 'tracking'], lien: accueil + '#suivi' },
    { mots: ['service', 'transport', 'national', 'express', 'mesure', 'palette', 'camion', 'marchandise'], lien: accueil + '#services' },
    { mots: ['propos', 'entreprise', 'societe', 'equipe', 'histoire', 'qui'], lien: accueil + '#apropos' },
    { mots: ['avis', 'temoignage', 'client', 'note'], lien: accueil + '#avis' },
    { mots: ['contact', 'telephone', 'appeler', 'mail', 'adresse', 'horaire', 'message', 'metz'], lien: lienVers('/contact') },
    { mots: ['mention', 'legal', 'siret', 'hebergeur', 'editeur'], lien: lienVers('/mentions-legales') },
    { mots: ['confidentialite', 'cookie', 'rgpd', 'donnee', 'vie privee'], lien: lienVers('/politique-de-confidentialite') }
  ];

  function normaliser(texte) {
    return texte.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
  }

  formRecherche.addEventListener('submit', function (event) {
    event.preventDefault();
    var saisie = normaliser(champRecherche.value);
    if (saisie === '') { messageRecherche.textContent = 'Tapez un mot, par exemple « devis » ou « suivi ».'; return; }

    var trouve = null;
    DESTINATIONS.forEach(function (d) {
      if (!trouve && d.lien && d.mots.some(function (m) { return saisie.indexOf(m) !== -1; })) trouve = d.lien;
    });

    if (!trouve) {
      messageRecherche.textContent = 'Aucun résultat pour « ' + champRecherche.value.trim() + ' ». Essayez « devis », « suivi », « services » ou « contact ».';
      return;
    }
    messageRecherche.textContent = '';
    search.classList.remove('open');
    btnSearch.setAttribute('aria-expanded', 'false');
    window.location.href = trouve;
  });

  // ---------- menu actif au défilement ----------
  var liens = Array.prototype.slice.call(document.querySelectorAll('nav.menu a'));
  var sections = liens.map(function (a) {
    // les liens sont de la forme « /#services » : on ne garde que les ancres de la page courante
    if (a.pathname !== window.location.pathname || !a.hash) return null;
    return document.getElementById(a.hash.slice(1));
  });
  var surAccueil = window.location.pathname === (liens[0] ? liens[0].pathname : '/');

  // sur les autres pages, on surligne le lien qui pointe vers la page affichée
  if (!surAccueil) {
    liens.forEach(function (a) { a.classList.toggle('active', a.pathname === window.location.pathname && !a.hash); });
  }

  function majMenu() {
    if (!surAccueil || document.body.classList.contains('menu-ouvert')) return;
    var y = window.scrollY + 150;
    var actif = 0, plusBas = -1;

    // on retient la section la plus basse déjà atteinte, pas la dernière de la liste
    sections.forEach(function (s, i) {
      if (s && s.offsetTop <= y && s.offsetTop > plusBas) { plusBas = s.offsetTop; actif = i; }
    });
    liens.forEach(function (a, i) { a.classList.toggle('active', i === actif); });
  }
  window.addEventListener('scroll', majMenu, { passive: true });
  liens.forEach(function (lien) {
    lien.addEventListener('click', function () {
      liens.forEach(function (a) { a.classList.remove('active'); });
      lien.classList.add('active');
    });
  });
  majMenu();

  // ---------- animations au défilement ----------
  var elements = document.querySelectorAll('.rev');
  if ('IntersectionObserver' in window) {
    var obs = new IntersectionObserver(function (entrees) {
      entrees.forEach(function (e, i) {
        if (e.isIntersecting) {
          setTimeout(function () { e.target.classList.add('in'); }, i * 70);
          obs.unobserve(e.target);
        }
      });
    }, { threshold: 0.12 });
    elements.forEach(function (el) { obs.observe(el); });
  } else {
    elements.forEach(function (el) { el.classList.add('in'); });
  }

  // ---------- diaporama animé de la bande plateforme ----------
  var bande = document.getElementById('plateforme');
  var vues = bande ? bande.querySelectorAll('.video-img .slide') : [];
  var vue = 0, boucle = null;

  function suivante() {
    vues[vue].classList.remove('on');
    vue = (vue + 1) % vues.length;
    var el = vues[vue];
    el.classList.remove('on');
    void el.offsetWidth;          // relance l'animation de zoom
    el.classList.add('on');
  }
  function lancer() { if (!boucle) boucle = setInterval(suivante, 2600); }
  function stopper() { clearInterval(boucle); boucle = null; }

  if (!bande || vues.length < 2) {
    // pas de diaporama sur cette page
  } else if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (entrees) {
      entrees.forEach(function (e) { e.isIntersecting ? lancer() : stopper(); });
    }, { threshold: 0.3 }).observe(bande);
  } else {
    lancer();
  }

  // ---------- la carte cliquée passe en bleu marine ----------
  var cartes = document.querySelectorAll('#services .card');
  cartes.forEach(function (carte) {
    carte.setAttribute('tabindex', '0');
    carte.setAttribute('role', 'button');

    function activer() {
      cartes.forEach(function (c) { c.classList.remove('hl'); });
      carte.classList.add('hl');
    }

    carte.addEventListener('click', activer);
    carte.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); activer(); }
    });
  });

  // ---------- fenêtre de détail des services ----------
  var ICO = {
    camion: '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#2a78cc" stroke-width="1.8"><path d="M3 7h11v8H3z"/><path d="M14 10h4l3 3v2h-7z"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>',
    eclair: '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#2a78cc" stroke-width="1.8"><path d="M13 2L4 14h7l-1 8 9-12h-7z"/></svg>',
    repere: '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#2a78cc" stroke-width="1.8"><path d="M12 21s7-6 7-11a7 7 0 1 0-14 0c0 5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>',
    boite: '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#2a78cc" stroke-width="1.8"><path d="M3 8l9-5 9 5z"/><path d="M3 8v8l9 5 9-5V8"/></svg>',
    bouclier: '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#2a78cc" stroke-width="1.8"><path d="M12 3l7 3v6c0 4.5-3 7.8-7 9-4-1.2-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/></svg>',
    bulle: '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#2a78cc" stroke-width="1.8"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9 9 0 0 1-3.8-.8L3 21l1.9-4.6A8.4 8.4 0 0 1 12 3a8.4 8.4 0 0 1 9 8.5z"/></svg>'
  };

  var SERVICES = {
    national: { titre: 'Transport national', icone: ICO.camion,
      texte: 'Nous acheminons vos marchandises partout en France, au départ du Grand Est, en lot complet ou en lot partiel.',
      points: ['Lots complets et lots partiels', 'Enlèvement et livraison sur rendez-vous', 'Véhicules adaptés à votre chargement'] },
    express: { titre: 'Transport express', icone: ICO.eclair,
      texte: 'Quand vos délais sont serrés, nous organisons un enlèvement rapide et une livraison en urgence.',
      points: ['Prise en charge le jour même selon disponibilité', 'Trajet direct, sans rupture de charge', 'Information à chaque étape'] },
    suivi: { titre: 'Suivi des envois', icone: ICO.repere,
      texte: 'Vous suivez votre marchandise étape par étape, du chargement jusqu’à la livraison.',
      points: ['Numéro de suivi pour chaque expédition', 'Statut mis à jour à chaque étape', 'Preuve de livraison transmise'],
      action: { texte: 'Suivre mon envoi →', lien: '#suivi' } },
    mesure: { titre: 'Transport sur mesure', icone: ICO.boite,
      texte: 'Marchandise volumineuse, fragile ou hors gabarit : nous étudions une solution adaptée à votre besoin.',
      points: ['Étude de faisabilité avant enlèvement', 'Matériel de calage et d’arrimage', 'Devis détaillé avant toute opération'] },
    securite: { titre: 'Sécurité et fiabilité', icone: ICO.bouclier,
      texte: 'Vos marchandises voyagent dans des véhicules entretenus, conduits par des chauffeurs formés.',
      points: ['Véhicules contrôlés régulièrement', 'Marchandises assurées pendant le transport', 'Délais annoncés puis tenus'] },
    accompagnement: { titre: 'Accompagnement', icone: ICO.bulle,
      texte: 'Un interlocuteur unique répond à vos questions avant, pendant et après le transport.',
      points: ['Un contact dédié par dossier', 'Réponse sous 24 heures ouvrées', 'Conseils sur l’emballage et les délais'],
      action: { texte: 'Nous contacter →', lien: '#contact' } }
  };

  var modal = document.getElementById('modal');

  function ouvrir(cle) {
    var s = SERVICES[cle];
    if (!s) return;
    document.getElementById('modal-icon').innerHTML = s.icone;
    document.getElementById('modal-title').textContent = s.titre;
    document.getElementById('modal-text').textContent = s.texte;
    document.getElementById('modal-list').innerHTML = s.points.map(function (p) {
      return '<li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2a78cc" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>' + p + '</li>';
    }).join('');
    var bouton = document.getElementById('modal-action');
    if (!bouton.dataset.devis) bouton.dataset.devis = bouton.getAttribute('href');   // lien Twig vers /devis
    var lien = s.action ? s.action.lien : bouton.dataset.devis;
    if (lien === '#contact') {
      var lienContact = document.querySelector('nav.menu a[href$="/contact"]');
      lien = lienContact ? lienContact.getAttribute('href') : '/contact';
    }
    bouton.textContent = s.action ? s.action.texte : 'Demander un devis →';
    bouton.setAttribute('href', lien);
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function fermer() { modal.classList.remove('open'); document.body.style.overflow = ''; }

  document.querySelectorAll('[data-service]').forEach(function (lien) {
    lien.setAttribute('href', '#');
    lien.addEventListener('click', function (event) {
      event.preventDefault();
      ouvrir(lien.getAttribute('data-service'));
    });
  });
  modal.addEventListener('click', function (event) { if (event.target.hasAttribute('data-close')) fermer(); });
  document.addEventListener('keydown', function (event) { if (event.key === 'Escape') fermer(); });

  // ---------- suivi d'envoi : API interne + vraie carte ----------
  // 1. le numéro saisi est envoyé à l'API du site (/suivi/{numéro}), qui lit la base de données ;
  // 2. la carte OpenStreetMap (bibliothèque Leaflet) n'est chargée qu'après une recherche réussie,
  //    pour ne contacter aucun service externe tant que le visiteur ne suit pas d'envoi.
  var form = document.getElementById('track-form');
  var carteDessin = document.querySelector('.map-card svg.map');
  var carteReelle = document.getElementById('carte-reelle');
  var boutonsZoom = document.querySelectorAll('.map-card .zoom button');
  var L = null, carteLeaflet = null, calqueTrajet = null;

  // zoom : agit sur la vraie carte si elle est affichée, sinon sur le dessin
  var echelle = 1, ZOOM_MIN = 1, ZOOM_MAX = 2.5, ZOOM_PAS = 0.5;
  function majBoutonsZoom() {
    if (boutonsZoom.length !== 2) return;
    if (carteLeaflet) {
      boutonsZoom[0].disabled = carteLeaflet.getZoom() >= carteLeaflet.getMaxZoom();
      boutonsZoom[1].disabled = carteLeaflet.getZoom() <= carteLeaflet.getMinZoom();
    } else {
      boutonsZoom[0].disabled = echelle >= ZOOM_MAX;
      boutonsZoom[1].disabled = echelle <= ZOOM_MIN;
    }
  }
  function zoomer(sens) {
    if (carteLeaflet) { if (sens > 0) { carteLeaflet.zoomIn(); } else { carteLeaflet.zoomOut(); } return; }
    echelle = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, echelle + sens * ZOOM_PAS));
    carteDessin.style.transform = 'scale(' + echelle + ')';
    majBoutonsZoom();
  }
  if (carteDessin && boutonsZoom.length === 2) {
    carteDessin.style.transition = 'transform .3s ease';
    carteDessin.style.transformOrigin = '55% 50%';   // centre du trajet dessiné
    boutonsZoom[0].addEventListener('click', function () { zoomer(1); });
    boutonsZoom[1].addEventListener('click', function () { zoomer(-1); });
    majBoutonsZoom();
  }

  function chargerLeaflet() {
    if (L) return Promise.resolve(L);
    return Promise.all([import('leaflet'), import('leaflet/dist/leaflet.min.css')]).then(function (modules) {
      L = modules[0].default || modules[0];
      return L;
    });
  }

  function repere(classe, ville) {
    return L.marker([ville.latitude, ville.longitude], {
      icon: L.divIcon({ className: '', html: '<span class="repere ' + classe + '"></span>', iconSize: [22, 22], iconAnchor: [11, 11] }),
      title: ville.ville,
      keyboard: false
    }).bindTooltip(ville.ville, { permanent: true, direction: 'top', offset: [0, -12], className: 'repere-nom' });
  }

  function masquerCarte() {
    if (carteReelle) { carteReelle.hidden = true; carteReelle.parentNode.classList.remove('carte-active'); }
    if (carteDessin) carteDessin.style.visibility = '';
    if (carteLeaflet) { carteLeaflet.remove(); carteLeaflet = null; calqueTrajet = null; }
    majBoutonsZoom();
  }

  function afficherCarte(depart, arrivee) {
    var localisable = depart.latitude !== null && arrivee.latitude !== null;
    if (!carteReelle || !localisable) { masquerCarte(); return Promise.resolve(false); }

    return chargerLeaflet().then(function () {
      carteReelle.hidden = false;
      carteReelle.parentNode.classList.add('carte-active');   // la carte s'agrandit pour montrer le trajet sous l'encadré
      if (carteDessin) carteDessin.style.visibility = 'hidden';

      if (!carteLeaflet) {
        carteLeaflet = L.map(carteReelle, { zoomControl: false, scrollWheelZoom: false, minZoom: 4, maxZoom: 13 });
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>'
        }).addTo(carteLeaflet);
        calqueTrajet = L.layerGroup().addTo(carteLeaflet);
        carteLeaflet.on('zoomend', majBoutonsZoom);
      }

      var a = [depart.latitude, depart.longitude];
      var b = [arrivee.latitude, arrivee.longitude];

      // 1. la carte vient d'apparaître ou de s'agrandir : Leaflet recalcule sa taille,
      //    puis cadre le trajet sous l'encadré d'informations (sans animation)
      carteLeaflet.invalidateSize();
      var encadre = document.querySelector('.map-card .info');
      carteLeaflet.fitBounds(L.latLngBounds([a, b]), {
        paddingTopLeft: [50, (encadre ? encadre.offsetHeight : 0) + 60],
        paddingBottomRight: [50, 40],
        maxZoom: 9,
        animate: false
      });

      // 2. seulement ensuite, le trajet et les repères : leurs étiquettes se placent sur la vue définitive
      calqueTrajet.clearLayers();
      // trajet à vol d'oiseau : le tracé routier exact demanderait un service d'itinéraire en plus
      L.polyline([a, b], { color: '#2a78cc', weight: 4, opacity: 0.9, dashArray: '10 8' }).addTo(calqueTrajet);
      repere('repere-depart', depart).addTo(calqueTrajet);
      repere('repere-arrivee', arrivee).addTo(calqueTrajet);
      majBoutonsZoom();
      return true;
    }).catch(function () { masquerCarte(); return false; });
  }

  if (form) {
    var champRef = document.getElementById('ref');
    var boutonSuivi = form.querySelector('button[type="submit"]');
    var erreur = document.getElementById('track-err');
    var etapes = document.querySelectorAll('#steps .step');
    var champ = function (id) { return document.getElementById(id); };

    var afficherEtape = function (numero) {
      etapes.forEach(function (el, i) {
        el.classList.toggle('done', i < numero - 1);
        el.classList.toggle('now', i === numero - 1);
      });
    };

    var formaterDate = function (iso) {
      var d = new Date(iso);
      if (isNaN(d.getTime())) return '—';
      return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }) +
        ' — ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    };

    var reinitialiser = function () {
      afficherEtape(0);
      champ('map-ref').textContent = 'Votre envoi';
      champ('map-tag').textContent = 'En attente';
      champ('map-tag').className = 'tag tag-attente';
      champ('map-depart').textContent = '—';
      champ('map-arrivee').textContent = '—';
      champ('map-maj').textContent = 'Saisissez votre numéro';
      masquerCarte();
    };

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var saisie = champRef.value.trim();
      if (saisie === '') { erreur.textContent = 'Veuillez saisir votre numéro de suivi.'; return; }
      if (!/^[A-Za-z0-9 -]{6,40}$/.test(saisie)) { erreur.textContent = 'Ce numéro de suivi n’est pas valide. Exemple : MOSL-7K4P-2QX9.'; return; }

      erreur.textContent = '';
      boutonSuivi.disabled = true;
      form.setAttribute('aria-busy', 'true');

      fetch(form.dataset.url.replace('REFERENCE', encodeURIComponent(saisie)), { headers: { Accept: 'application/json' } })
        .then(function (reponse) {
          return reponse.json().then(function (donnees) { return { ok: reponse.ok, donnees: donnees }; });
        })
        .then(function (resultat) {
          if (!resultat.ok) {
            reinitialiser();
            erreur.textContent = resultat.donnees.erreur || 'Aucun envoi ne correspond à ce numéro de suivi.';
            return null;
          }
          var envoi = resultat.donnees;
          afficherEtape(envoi.etape.numero);
          champ('map-ref').textContent = envoi.reference;
          champ('map-tag').textContent = envoi.etape.libelle;
          champ('map-tag').className = 'tag' + (envoi.etape.code === 'livree' ? '' : ' tag-cours');
          champ('map-depart').textContent = envoi.depart.ville;
          champ('map-arrivee').textContent = envoi.arrivee.ville;
          champ('map-maj').textContent = formaterDate(envoi.misAJourLe);
          return afficherCarte(envoi.depart, envoi.arrivee);
        })
        .catch(function () {
          erreur.textContent = 'Le service de suivi est momentanément indisponible. Réessayez dans quelques instants.';
        })
        .then(function () {
          boutonSuivi.disabled = false;
          form.removeAttribute('aria-busy');
        });
    });
  }

  // ---------- témoignages ----------
  var track = document.getElementById('track');
  if (!track) return;   // pas de carrousel sur cette page : fin du script
  var slides = track.children.length;
  var dots = document.getElementById('dots');
  var index = 0;

  for (var i = 0; i < slides; i++) {
    var d = document.createElement('button');
    d.setAttribute('aria-label', 'Témoignages ' + (i + 1));
    d.addEventListener('click', (function (n) { return function () { aller(n); }; })(i));
    dots.appendChild(d);
  }

  function aller(n) {
    index = (n + slides) % slides;
    track.style.transform = 'translateX(' + (-index * 100) + '%)';
    Array.prototype.forEach.call(dots.children, function (b, i) { b.classList.toggle('on', i === index); });
  }
  document.getElementById('prev').addEventListener('click', function () { aller(index - 1); });
  document.getElementById('next').addEventListener('click', function () { aller(index + 1); });
  aller(0);

  // défilement automatique : il démarre quand la section apparaît à l'écran
  var auto = null;
  function demarrer() { if (!auto) auto = setInterval(function () { aller(index + 1); }, 6000); }
  function arreter() { clearInterval(auto); auto = null; }

  var section = document.getElementById('avis');
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (entrees) {
      entrees.forEach(function (e) { if (e.isIntersecting) { demarrer(); } else { arreter(); } });
    }, { threshold: 0.35 }).observe(section);
  } else {
    demarrer();
  }

  // on met en pause au survol et au clavier, comme le demande l'accessibilité
  section.addEventListener('mouseenter', arreter);
  section.addEventListener('mouseleave', demarrer);
  section.addEventListener('focusin', arreter);
  section.addEventListener('focusout', demarrer);

})();
