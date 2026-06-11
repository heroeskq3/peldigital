/* PEL Digital — Layout compartido: nav drawer, dropdowns, tema claro/oscuro.
   Carga en TODAS las páginas autenticadas (scripts.php lo inyecta siempre). */

(function () {
  'use strict';

  // ── Tema ──────────────────────────────────────────────────────────────────

  const tema = () => document.documentElement.getAttribute('data-theme') || 'light';

  function actualizarIconoTema() {
    const oscuro = tema() === 'dark';
    const btn  = document.getElementById('btnTheme');
    const btnM = document.getElementById('btnThemeM');
    const lbl  = document.getElementById('themeLabelM');
    if (btn)  btn.querySelector('i').className  = oscuro ? 'bi bi-sun' : 'bi bi-moon';
    if (btnM) btnM.querySelector('i').className = oscuro ? 'bi bi-sun' : 'bi bi-moon';
    if (lbl)  lbl.textContent = oscuro ? 'Modo claro' : 'Modo oscuro';
  }

  function alternarTema() {
    const nuevo = tema() === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', nuevo);
    localStorage.setItem('cr-theme', nuevo);
    actualizarIconoTema();
    // Notifica a scripts de página (app.js actualiza el mapa, etc.)
    document.dispatchEvent(new CustomEvent('themechange', { detail: { tema: nuevo } }));
  }

  // ── Nav drawer + dropdowns ─────────────────────────────────────────────────

  function setupNav() {
    const nav      = document.getElementById('mainNav');
    const toggle   = document.getElementById('btnMenu');
    const backdrop = document.getElementById('navBackdrop');
    if (!nav || !toggle) return;

    const mq      = matchMedia('(max-width: 820px)');
    const esMovil = () => mq.matches;

    const cerrarDropdowns = () => {
      nav.querySelectorAll('.nav-item.open').forEach(it => {
        it.classList.remove('open');
        it.querySelector('.nav-link').setAttribute('aria-expanded', 'false');
      });
      nav.querySelectorAll('.dropdown-submenu.open').forEach(it => {
        it.classList.remove('open');
        it.querySelector('.submenu-trigger')?.setAttribute('aria-expanded', 'false');
      });
    };

    const cerrarSubmenusHermanos = submenu => {
      submenu.parentElement
        .querySelectorAll(':scope > .dropdown-submenu.open')
        .forEach(it => {
          if (it === submenu) return;
          it.classList.remove('open');
          it.querySelector('.submenu-trigger')?.setAttribute('aria-expanded', 'false');
        });
    };

    const abrirDrawer = () => {
      nav.classList.add('open');
      backdrop?.classList.remove('d-none');
      toggle.setAttribute('aria-expanded', 'true');
      document.body.classList.add('nav-open');
      document.body.style.overflow = 'hidden';
    };

    const cerrarDrawer = () => {
      nav.classList.remove('open');
      backdrop?.classList.add('d-none');
      toggle.setAttribute('aria-expanded', 'false');
      document.body.classList.remove('nav-open');
      document.body.style.overflow = '';
      cerrarDropdowns();
    };

    const cerrarTodo = () => { cerrarDropdowns(); if (esMovil()) cerrarDrawer(); };

    // Expuesto para que app.js y admin.js cierren el drawer tras sus acciones
    window.navCerrarTodo = cerrarTodo;

    toggle.addEventListener('click', () =>
      nav.classList.contains('open') ? cerrarDrawer() : abrirDrawer()
    );
    document.getElementById('btnMenuClose')?.addEventListener('click', cerrarDrawer);
    backdrop?.addEventListener('click', cerrarDrawer);

    // Nivel 1: menú padre (Análisis / Admin)
    nav.querySelectorAll('.nav-item.has-dropdown > .nav-link').forEach(link => {
      link.addEventListener('click', e => {
        e.stopPropagation();
        const item   = link.parentElement;
        const abierto = item.classList.contains('open');
        cerrarDropdowns();
        if (!abierto) {
          item.classList.add('open');
          link.setAttribute('aria-expanded', 'true');
        }
      });
    });

    // Nivel 2: subcategorías
    nav.querySelectorAll('.dropdown-submenu > .submenu-trigger').forEach(link => {
      link.addEventListener('click', e => {
        e.stopPropagation();
        const item   = link.parentElement;
        const abierto = item.classList.contains('open');
        cerrarSubmenusHermanos(item);
        item.classList.toggle('open', !abierto);
        link.setAttribute('aria-expanded', String(!abierto));
      });
    });

    // Cerrar al hacer clic fuera (desktop)
    document.addEventListener('click', e => {
      if (!nav.contains(e.target) && !toggle.contains(e.target)) cerrarDropdowns();
    });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarTodo(); });
    mq.addEventListener('change', ev => { if (!ev.matches) cerrarDrawer(); });

    // Controles de tema y reset del drawer móvil
    document.getElementById('btnTheme')?.addEventListener('click', alternarTema);
    document.getElementById('btnThemeM')?.addEventListener('click', alternarTema);
    document.getElementById('btnResetM')?.addEventListener('click', () => {
      document.dispatchEvent(new Event('navreset'));
      cerrarTodo();
    });
  }

  // Sincronizar icono al cargar (el tema ya fue aplicado por head.php inline)
  actualizarIconoTema();

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupNav);
  } else {
    setupNav();
  }
})();
