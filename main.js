/* Email lives on <body data-email="..."> in index.html. Change it there. */
(() => {
  const email = (document.body.dataset.email || "hello@aroosa.co").trim();

  document.querySelectorAll("[data-email-link]").forEach((node) => {
    if (node.tagName === "A") node.setAttribute("href", `mailto:${email}`);
    if (!node.querySelector("svg")) node.textContent = email;
  });

  const year = document.querySelector("[data-year]");
  if (year) year.textContent = String(new Date().getFullYear());

  const track = document.querySelector(".marquee-track");
  const firstGroup = track && track.querySelector(".marquee-group");
  if (track && firstGroup) {
    const seed = [...firstGroup.children].map((node) => node.cloneNode(true));
    const fillMarquee = () => {
      const min = track.parentElement.clientWidth + 32;
      firstGroup.replaceChildren();
      let guard = 0;
      do {
        seed.forEach((node) => firstGroup.appendChild(node.cloneNode(true)));
        guard += 1;
      } while (firstGroup.scrollWidth < min && guard < 8);
      track.replaceChildren(firstGroup, firstGroup.cloneNode(true));
    };
    fillMarquee();
    window.addEventListener("resize", fillMarquee);
  }

  const intro = document.getElementById("intro");
  if (intro && !document.documentElement.classList.contains("skip-intro")) {
    const finishIntro = () => {
      try {
        sessionStorage.setItem("aroosa-intro", "1");
      } catch (err) {
        /* storage may be blocked */
      }
      intro.remove();
    };
    intro.addEventListener("animationend", (event) => {
      if (event.animationName === "introLeave") finishIntro();
    });
    setTimeout(finishIntro, 4000);
  } else if (intro) {
    intro.remove();
  }

  const themeBtn = document.querySelector(".theme-toggle");
  const themeMeta = document.querySelector('meta[name="theme-color"]');

  function applyTheme(theme) {
    const dark = theme === "dark";
    document.documentElement.setAttribute("data-theme", dark ? "dark" : "light");
    if (themeMeta) themeMeta.setAttribute("content", dark ? "#070e18" : "#f3f7fc");
    if (themeBtn) {
      themeBtn.setAttribute("aria-pressed", dark ? "true" : "false");
      themeBtn.setAttribute("aria-label", dark ? "Switch to light mode" : "Switch to dark mode");
    }
  }

  applyTheme(document.documentElement.getAttribute("data-theme") || "light");

  if (themeBtn) {
    themeBtn.addEventListener("click", () => {
      const next = document.documentElement.getAttribute("data-theme") === "dark" ? "light" : "dark";
      try {
        localStorage.setItem("aroosa-theme", next);
      } catch (err) {
        /* storage may be blocked */
      }
      applyTheme(next);
    });
  }

  const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  const typed = document.getElementById("typed");
  if (typed) {
    const phrases = [
      ["Care in ", "the hiring."],
      ["Clarity in ", "the books."],
      ["The right ", "names."],
      ["The month, ", "readable."],
      ["The next ", "conversation."]
    ];
    const paint = (phrase, count) => {
      typed.replaceChildren();
      const lead = document.createElement("span");
      lead.textContent = phrase[0].slice(0, Math.min(count, phrase[0].length));
      typed.appendChild(lead);
      if (count > phrase[0].length) {
        const em = document.createElement("em");
        em.textContent = phrase[1].slice(0, count - phrase[0].length);
        typed.appendChild(em);
      }
    };
    if (reduceMotion) {
      paint(phrases[0], phrases[0][0].length + phrases[0][1].length);
      const caret = document.querySelector(".caret");
      if (caret) caret.remove();
    } else {
      let phrase = 0;
      let count = 0;
      let deleting = false;
      const step = () => {
        const current = phrases[phrase];
        const total = current[0].length + current[1].length;
        paint(current, count);
        if (!deleting) {
          if (count < total) {
            count += 1;
            window.setTimeout(step, count === 1 ? 260 : 52);
          } else {
            deleting = true;
            window.setTimeout(step, 1400);
          }
          return;
        }
        if (count > 0) {
          count -= 1;
          window.setTimeout(step, 28);
          return;
        }
        deleting = false;
        phrase = (phrase + 1) % phrases.length;
        window.setTimeout(step, 280);
      };
      const start = document.documentElement.classList.contains("skip-intro") ? 240 : 1700;
      window.setTimeout(step, start);
    }
  }

  const nav = document.getElementById("nav");
  const hero = document.getElementById("top");
  const progress = document.querySelector(".progress");
  const toggle = document.querySelector(".nav-toggle");
  const panel = document.getElementById("nav-panel");
  let lastFocus = null;
  let scrollTick = false;

  function onScroll() {
    const y = window.scrollY;
    const threshold = hero ? Math.max(hero.offsetHeight - 72, 24) : 24;
    nav.classList.toggle("is-solid", y > threshold);
    if (progress) {
      const max = document.documentElement.scrollHeight - window.innerHeight;
      const ratio = max > 0 ? Math.min(y / max, 1) : 0;
      progress.style.transform = `scaleX(${ratio})`;
    }
  }

  window.addEventListener(
    "scroll",
    () => {
      if (scrollTick) return;
      scrollTick = true;
      requestAnimationFrame(() => {
        onScroll();
        scrollTick = false;
      });
    },
    { passive: true }
  );
  onScroll();

  function closeMenu() {
    if (!panel || panel.hidden) return;
    panel.hidden = true;
    toggle.setAttribute("aria-expanded", "false");
    nav.classList.remove("is-open");
    document.body.classList.remove("menu-open");
    if (lastFocus && typeof lastFocus.focus === "function") lastFocus.focus();
  }

  function openMenu() {
    lastFocus = document.activeElement;
    panel.hidden = false;
    toggle.setAttribute("aria-expanded", "true");
    nav.classList.add("is-open");
    document.body.classList.add("menu-open");
    const first = panel.querySelector("a, button");
    if (first) first.focus();
  }

  if (toggle && panel) {
    toggle.addEventListener("click", () => {
      if (panel.hidden) openMenu();
      else closeMenu();
    });

    panel.querySelectorAll("a").forEach((link) => {
      link.addEventListener("click", closeMenu);
    });

    panel.querySelectorAll("[data-close]").forEach((button) => {
      button.addEventListener("click", closeMenu);
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") closeMenu();
    });

    window.addEventListener("resize", () => {
      if (window.innerWidth > 920) closeMenu();
    });
  }

  const revealNodes = document.querySelectorAll(".reveal");
  if (reduceMotion || !("IntersectionObserver" in window)) {
    revealNodes.forEach((node) => node.classList.add("is-in"));
  } else {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          entry.target.classList.add("is-in");
          observer.unobserve(entry.target);
        });
      },
      { threshold: 0.08, rootMargin: "0px 0px -4% 0px" }
    );
    revealNodes.forEach((node) => observer.observe(node));
  }

  const form = document.getElementById("brief");
  const success = document.getElementById("form-success");
  const note = document.getElementById("form-note");
  const again = document.getElementById("write-again");

  function clearInvalid() {
    if (!form) return;
    form.querySelectorAll(".is-invalid").forEach((field) => {
      field.classList.remove("is-invalid");
      const control = field.querySelector("input, select, textarea");
      if (control) control.removeAttribute("aria-invalid");
    });
    if (note) note.textContent = "";
  }

  function resetForm() {
    if (!form || !success) return;
    form.reset();
    clearInvalid();
    form.hidden = false;
    success.hidden = true;
  }

  document.querySelectorAll("[data-select]").forEach((link) => {
    link.addEventListener("click", () => {
      if (!form) return;
      if (form.hidden) resetForm();
      const select = form.querySelector("[name='service']");
      if (select) select.value = link.dataset.select;
    });
  });

  if (again) again.addEventListener("click", resetForm);

  if (form) {
    form.action = `mailto:${email}`;
    form.addEventListener("input", (event) => {
      const field = event.target.closest(".field");
      if (!field) return;
      field.classList.remove("is-invalid");
      event.target.removeAttribute("aria-invalid");
      if (note && !form.querySelector(".is-invalid")) note.textContent = "";
    });

    form.addEventListener("submit", (event) => {
      event.preventDefault();
      clearInvalid();

      const honeypot = form.querySelector("[name='website']");
      if (honeypot && honeypot.value.trim() !== "") {
        form.hidden = true;
        success.hidden = false;
        return;
      }

      const checks = [
        ["name", (value) => value.trim().length > 1, "Please add your name."],
        ["email", (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim()), "Please add an email that can be answered."],
        ["service", (value) => value.trim() !== "", "Please choose what you need."],
        ["message", (value) => value.trim().length > 8, "A few lines are enough — what do you need?"]
      ];

      const problems = [];
      let firstInvalid = null;

      checks.forEach(([name, test, message]) => {
        const control = form.querySelector(`[name='${name}']`);
        const field = control.closest(".field");
        if (test(control.value)) return;
        field.classList.add("is-invalid");
        control.setAttribute("aria-invalid", "true");
        problems.push(message);
        if (!firstInvalid) firstInvalid = control;
      });

      if (problems.length) {
        note.textContent = problems[0];
        firstInvalid.focus();
        return;
      }

      const data = new FormData(form);
      const service = String(data.get("service") || "").trim();
      const body = [
        `Name: ${String(data.get("name") || "").trim()}`,
        `Company: ${String(data.get("company") || "").trim() || "—"}`,
        `Regarding: ${service}`,
        "",
        String(data.get("message") || "").trim()
      ].join("\n");

      const subject = `A conversation — ${service}`;
      window.location.href = `mailto:${email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
      form.hidden = true;
      success.hidden = false;
      success.focus();
    });
  }
})();
