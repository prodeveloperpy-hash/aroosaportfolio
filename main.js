/* Email lives on <body data-email="..."> in index.html. Change it there. */
(() => {
  const email = (document.body.dataset.email || "info@recruitexa.com").trim();

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

  const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  const stepNotes = document.getElementById("step-notes");
  const methodSection = document.getElementById("method");
  const startMethodFeed = () => {
    if (!stepNotes || stepNotes.dataset.started === "1") return;
    stepNotes.dataset.started = "1";
    const steps = [
      { tag: "Brief", text: "The need, written plainly." },
      { tag: "Scope", text: "What finished means." },
      { tag: "Work", text: "Search, people, or the brief." },
      { tag: "Review", text: "What the work showed." },
      { tag: "Stay", text: "Remain, or close the file." }
    ];
    steps.forEach((step, index) => {
      window.setTimeout(() => {
        const card = document.createElement("div");
        card.className = "step-note";
        card.innerHTML = `<strong>${step.tag}</strong><span>${step.text}</span>`;
        stepNotes.appendChild(card);
        window.setTimeout(() => {
          card.classList.add("is-out");
          window.setTimeout(() => card.remove(), 400);
        }, 4200);
      }, index * 1400);
    });
  };
  if (stepNotes && methodSection && "IntersectionObserver" in window) {
    const watch = new IntersectionObserver((entries) => {
      if (entries.some((entry) => entry.isIntersecting)) {
        startMethodFeed();
        watch.disconnect();
      }
    }, { threshold: 0.28 });
    watch.observe(methodSection);
  } else if (stepNotes) {
    startMethodFeed();
  }

  const pingStack = document.getElementById("ping-stack");
  if (pingStack) {
    const topics = [
      { need: "HR for your business", select: "HR consulting & operations" },
      { need: "recruitment", select: "Recruitment & talent acquisition" },
      { need: "talent sourcing", select: "Talent sourcing & mapping" },
      { need: "a CV or career page", select: "CV & career services" },
      { need: "reverse recruiting", select: "Reverse recruiting" },
      { need: "agency support", select: "Recruitment agency support" },
      { need: "a hiring strategy", select: "Additional talent services" },
      { need: "business development", select: "Business development & lead generation" }
    ];
    let index = 0;
    const email = (document.body.dataset.email || "info@recruitexa.com").trim();
    const pickService = (select) => {
      const field = document.querySelector("#brief [name='service']");
      if (field) field.value = select;
    };
    const closePing = (card) => {
      card.classList.add("is-out");
      window.setTimeout(() => card.remove(), 380);
    };
    const showConnect = (topic) => {
      const card = document.createElement("article");
      card.className = "ping";
      card.innerHTML = `<img class="ping-face" src="assets/real-portrait.jpg" alt=""><div><p class="ping-who">Marium</p><p>WhatsApp, or a note by email?</p><div class="ping-actions"><a href="https://wa.me/17868877796" target="_blank" rel="noopener noreferrer">WhatsApp</a><a class="ghost" href="#contact">Email</a></div></div><button class="ping-x" type="button" aria-label="Close">×</button>`;
      card.querySelector(".ghost").addEventListener("click", () => pickService(topic.select));
      card.querySelector(".ping-x").addEventListener("click", () => closePing(card));
      pingStack.appendChild(card);
      while (pingStack.children.length > 3) {
        pingStack.firstElementChild.classList.add("is-out");
        window.setTimeout(() => pingStack.firstElementChild && pingStack.firstElementChild.remove(), 380);
      }
    };
    const showAsk = () => {
      if (index >= topics.length) {
        if (!pingStack.children.length) pingStack.remove();
        return;
      }
      const topic = topics[index];
      index += 1;
      const card = document.createElement("article");
      card.className = "ping";
      card.innerHTML = `<img class="ping-face" src="assets/real-portrait.jpg" alt=""><div><p class="ping-who">Marium</p><p>I am Marium. You are finding ${topic.need}?</p><div class="ping-actions"><button type="button">Click here to connect</button></div></div><button class="ping-x" type="button" aria-label="Close">×</button>`;
      card.querySelector(".ping-actions button").addEventListener("click", () => {
        pickService(topic.select);
        closePing(card);
        showConnect(topic);
      });
      card.querySelector(".ping-x").addEventListener("click", () => {
        closePing(card);
        window.setTimeout(showAsk, 700);
      });
      pingStack.appendChild(card);
      while (pingStack.children.length > 3) {
        const old = pingStack.firstElementChild;
        old.classList.add("is-out");
        window.setTimeout(() => old.remove(), 380);
      }
      window.setTimeout(() => {
        if (card.isConnected && !card.classList.contains("is-out")) {
          closePing(card);
          showAsk();
        }
      }, 8000);
    };
    window.setTimeout(showAsk, 2000);
  }

  const themeBtn = document.querySelector(".theme-toggle");
  const themeMeta = document.querySelector('meta[name="theme-color"]');

  function applyTheme(theme) {
    const dark = theme === "dark";
    document.documentElement.setAttribute("data-theme", dark ? "dark" : "light");
    if (themeMeta) themeMeta.setAttribute("content", dark ? "#071226" : "#123a8c");
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

  const typed = document.getElementById("typed");
  if (typed) {
    const phrases = [
      ["Care in ", "the hiring."],
      ["The right ", "names."],
      ["The search, ", "quiet."],
      ["The short ", "list."],
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

  const showRoot = document.getElementById("service-show");
  if (showRoot) {
    const tabs = [...showRoot.querySelectorAll(".show-rail button")];
    const panes = [...showRoot.querySelectorAll(".show-pane")];
    const progress = document.getElementById("service-progress");
    let cursor = 0;
    let inView = false;
    let hold = false;

    if (progress) {
      progress.style.gridTemplateColumns = `repeat(${tabs.length}, minmax(0, 1fr))`;
      progress.innerHTML = tabs.map(() => "<span><i></i></span>").join("");
    }
    const segs = progress ? [...progress.querySelectorAll("span")] : [];

    function markProgress() {
      segs.forEach((seg, i) => {
        seg.classList.toggle("is-done", i < cursor);
        seg.classList.toggle("is-on", i === cursor);
        const bar = seg.querySelector("i");
        if (!bar) return;
        bar.style.animation = "none";
        void bar.offsetWidth;
        if (i === cursor && !reduceMotion) {
          bar.style.animation = "";
        } else if (i < cursor || (reduceMotion && i === cursor)) {
          bar.style.transform = "scaleX(1)";
        } else {
          bar.style.transform = "scaleX(0)";
        }
      });
      tabs.forEach((tab, i) => {
        tab.classList.toggle("is-next", i === (cursor + 1) % tabs.length);
      });
    }

    function showService(id, scroll) {
      const index = panes.findIndex((pane) => pane.id === id);
      if (index < 0) return;
      cursor = index;
      panes.forEach((pane, i) => {
        const on = i === index;
        pane.classList.toggle("is-on", on);
        pane.hidden = !on;
        const photo = pane.querySelector(".show-photo");
        if (photo) photo.style.transform = "";
      });
      tabs.forEach((tab, i) => {
        const on = i === index;
        tab.setAttribute("aria-selected", on ? "true" : "false");
        tab.tabIndex = on ? 0 : -1;
      });
      markProgress();
      if (scroll) {
        document.getElementById("services").scrollIntoView({ behavior: reduceMotion ? "auto" : "smooth", block: "start" });
      }
    }

    function pausePlay() {
      showRoot.classList.toggle("is-paused", hold || !inView);
    }

    function nextService() {
      if (hold || !inView) return;
      cursor = (cursor + 1) % panes.length;
      showService(panes[cursor].id, false);
    }

    tabs.forEach((tab) => {
      tab.addEventListener("click", () => {
        showService(tab.getAttribute("aria-controls"), false);
        history.replaceState(null, "", "#" + tab.getAttribute("aria-controls"));
      });
    });

    function fromHash(scroll) {
      const id = location.hash.replace("#", "");
      if (panes.some((pane) => pane.id === id)) showService(id, scroll);
    }

    window.addEventListener("hashchange", () => fromHash(true));
    fromHash(false);
    markProgress();

    if (!reduceMotion) {
      segs.forEach((seg) => {
        const bar = seg.querySelector("i");
        if (!bar) return;
        bar.addEventListener("animationend", (event) => {
          if (event.animationName !== "showFill") return;
          if (!seg.classList.contains("is-on")) return;
          nextService();
        });
      });
      if ("IntersectionObserver" in window) {
        const watch = new IntersectionObserver(
          (entries) => {
            inView = entries.some((entry) => entry.isIntersecting);
            pausePlay();
          },
          { threshold: 0.35 }
        );
        watch.observe(showRoot);
      } else {
        inView = true;
      }
      showRoot.addEventListener("mouseenter", () => {
        hold = true;
        pausePlay();
      });
      showRoot.addEventListener("mouseleave", () => {
        hold = false;
        pausePlay();
      });
      showRoot.addEventListener("focusin", () => {
        hold = true;
        pausePlay();
      });
      showRoot.addEventListener("focusout", () => {
        hold = false;
        pausePlay();
      });
      pausePlay();
    }

    if (!reduceMotion) {
      showRoot.addEventListener("mousemove", (event) => {
        const photo = showRoot.querySelector(".show-pane.is-on .show-photo");
        if (!photo) return;
        const box = photo.getBoundingClientRect();
        const x = (event.clientX - box.left) / box.width - 0.5;
        const y = (event.clientY - box.top) / box.height - 0.5;
        if (Math.abs(x) > 0.7 || Math.abs(y) > 0.7) return;
        photo.style.transform = `rotateY(${(x * 8).toFixed(2)}deg) rotateX(${(-y * 6).toFixed(2)}deg)`;
      });
      showRoot.addEventListener("mouseleave", () => {
        showRoot.querySelectorAll(".show-photo").forEach((photo) => {
          photo.style.transform = "";
        });
      });
    }
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

  function bindSelect(link) {
    link.addEventListener("click", () => {
      if (!form) return;
      if (form.hidden) resetForm();
      const select = form.querySelector("[name='service']");
      if (select) select.value = link.dataset.select;
    });
  }

  document.querySelectorAll("[data-select]").forEach(bindSelect);

  if (again) again.addEventListener("click", resetForm);

  if (form) {
    form.addEventListener("input", (event) => {
      const field = event.target.closest(".field");
      if (!field) return;
      field.classList.remove("is-invalid");
      event.target.removeAttribute("aria-invalid");
      if (note && !form.querySelector(".is-invalid")) note.textContent = "";
    });

    form.addEventListener("submit", async (event) => {
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

      note.textContent = "Sending…";
      try {
        const response = await fetch("send.php", { method: "POST", body: new FormData(form) });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || !result.ok) {
          note.textContent = result.error || `The note could not be sent. Write to ${email}.`;
          return;
        }
      } catch (err) {
        note.textContent = `The note could not be sent. Write to ${email}.`;
        return;
      }

      form.hidden = true;
      success.hidden = false;
      success.focus();
    });
  }
})();
