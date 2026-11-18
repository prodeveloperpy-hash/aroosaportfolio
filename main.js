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
      { need: "recruitment", select: "Recruitment" },
      { need: "talent sourcing", select: "Talent Sourcing & Executive Search" },
      { need: "B2B lead generation", select: "B2B Lead Generation" },
      { need: "reverse recruitment", select: "Reverse Recruitment" },
      { need: "CV formatting", select: "CV Formatting & Optimization" }
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
      card.innerHTML = `<img class="ping-face" src="assets/real-portrait.jpg?v=p3" alt=""><div><p class="ping-who">Marium</p><p>Let’s talk on WhatsApp, or send a note.</p><div class="ping-actions"><a href="https://wa.me/17868877796" target="_blank" rel="noopener noreferrer">WhatsApp</a><a class="ghost" href="#contact">Email</a></div></div><button class="ping-x" type="button" aria-label="Close">×</button>`;
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
      card.innerHTML = `<img class="ping-face" src="assets/real-portrait.jpg?v=p3" alt=""><div><p class="ping-who">Need this desk?</p><p>Looking for ${topic.need}?</p><div class="ping-actions"><button type="button">Connect</button></div></div><button class="ping-x" type="button" aria-label="Close">×</button>`;
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
    if (themeMeta) themeMeta.setAttribute("content", dark ? "#0A1D3D" : "#0A1D3D");
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

  const heroBg = document.getElementById("hero-bg");
  const heroTitle = document.getElementById("hero-title");
  if (heroBg && heroTitle) {
    const slides = [
      {
        kicker: "Hands-on recruitment, talent sourcing & B2B pipeline support",
        title: "Care in the Hiring.<br>Clarity in Every Career Move.",
        lead: "Recruitexa helps startups, growing companies, and recruitment agencies hire technical and specialist talent, and build targeted B2B pipelines. You receive <strong>scored shortlists in 2 to 3 weeks</strong>, with a clear written scope before work begins.",
        diff: "Roles Filled. Careers Moved Forward."
      },
      {
        kicker: "Recruitment for founders & companies",
        title: "From a clear brief<br>to the person who starts.",
        lead: "End-to-end recruitment for technical, specialist, and US federal contracting roles. I source, screen, and present <strong>scored shortlists in 2 to 3 weeks</strong>.",
        diff: "A written scope before the search begins."
      },
      {
        kicker: "Talent sourcing & executive search",
        title: "Senior talent,<br>beyond the job boards.",
        lead: "I reach passive candidates through <strong>LinkedIn Recruiter, advanced Boolean, and Google X-ray search</strong> — then deliver qualified shortlists, including white-label support for recruitment agencies.",
        diff: "Mapped, approached, and ready to review."
      },
      {
        kicker: "B2B lead generation",
        title: "Verified decision-makers.<br>Lists you can work.",
        lead: "Ideal Customer Profile research, verified contacts, and CRM-ready prospect lists for agencies, founders, and sales teams — built to open real conversations.",
        diff: "Qualified names. A pipeline you can open."
      },
      {
        kicker: "Reverse recruitment",
        title: "Your next role,<br>searched with care.",
        lead: "I manage target-company research, applications, and recruiter outreach on your behalf — with <strong>25+ placements across the US and UAE</strong>.",
        diff: "You approve. I run the search."
      },
      {
        kicker: "CV formatting & optimization",
        title: "A resume recruiters<br>can read at a glance.",
        lead: "ATS-conscious structure, keyword alignment, and clean presentation — delivered in Word, Google Docs, and PDF within <strong>3 to 5 business days</strong>.",
        diff: "Clear, structured, and send-ready."
      }
    ];
    const images = [...heroBg.querySelectorAll("img")];
    const kicker = document.getElementById("hero-kicker");
    const lead = document.getElementById("hero-lead");
    const diff = document.getElementById("hero-diff");
    const copy = document.querySelector(".hero-copy");
    const dots = document.getElementById("hero-dots");
    let at = 0;
    let timer = 0;
    let fadeTimer = 0;
    let firstPaint = true;
    if (dots) {
      dots.innerHTML = slides.map((_, i) => `<button type="button" aria-label="Slide ${i + 1}"></button>`).join("");
    }
    const writeCopy = () => {
      const slide = slides[at];
      if (kicker) kicker.textContent = slide.kicker;
      heroTitle.innerHTML = slide.title;
      if (lead) lead.innerHTML = slide.lead;
      if (diff) diff.textContent = slide.diff;
      if (copy) copy.classList.remove("is-out");
    };
    const paint = (i) => {
      at = (i + slides.length) % slides.length;
      images.forEach((img, n) => img.classList.toggle("is-on", n === at));
      if (dots) {
        [...dots.children].forEach((dot, n) => dot.classList.toggle("is-on", n === at));
      }
      window.clearTimeout(fadeTimer);
      if (firstPaint || reduceMotion || !copy) {
        firstPaint = false;
        writeCopy();
        return;
      }
      copy.classList.add("is-out");
      fadeTimer = window.setTimeout(writeCopy, 480);
    };
    const play = () => {
      window.clearInterval(timer);
      if (reduceMotion) return;
      timer = window.setInterval(() => paint(at + 1), 7600);
    };
    paint(0);
    play();
    const prev = document.getElementById("hero-prev");
    const next = document.getElementById("hero-next");
    if (prev) prev.addEventListener("click", () => { paint(at - 1); play(); });
    if (next) next.addEventListener("click", () => { paint(at + 1); play(); });
    if (dots) {
      [...dots.children].forEach((dot, i) => {
        dot.addEventListener("click", () => { paint(i); play(); });
      });
    }
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
  if (showRoot && showRoot.querySelector(".show-pane")) {
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
      const value = link.dataset.select;
      try {
        sessionStorage.setItem("aroosa-select", value);
      } catch (err) {
        /* storage may be blocked */
      }
      if (!form) return;
      if (form.hidden) resetForm();
      const select = form.querySelector("[name='service']");
      if (select) select.value = value;
    });
  }

  document.querySelectorAll("[data-select]").forEach(bindSelect);

  const catalog = {
    recruitment: {
      title: "Recruitment",
      select: "Recruitment",
      img: "assets/pro-handshake.jpg",
      alt: "A hiring conversation sealed across the table.",
      lead: "From the brief to the person who starts.",
      who: "For founders and companies who need end-to-end recruitment and permanent hiring — technical, non-technical, executive, and specialist seats, including US federal contracting (SAM.gov).",
      includes: [
        "End-to-end recruitment and permanent hiring.",
        "Technical, non-technical, executive, and specialist recruitment.",
        "<strong>US federal contracting support (SAM.gov), sales, brokerage, finance, healthcare, and operations hiring.</strong>",
        "Candidate sourcing, screening, shortlisting, and interview coordination.",
        "Offer management, hiring closure, and employee onboarding support."
      ],
      how: [
        "You share the role, budget, and timeline. We agree a written scope before work begins.",
        "I personally source, screen, and score 3 to 5 candidates with interview notes.",
        "We review the shortlist together. Eligible placements include a 30-day replacement warranty."
      ],
      receive: "A scored shortlist of 3 to 5 vetted candidates in 2 to 3 weeks, with interview notes and a structured scorecard."
    },
    "talent-sourcing": {
      title: "Talent Sourcing & Executive Search",
      select: "Talent Sourcing & Executive Search",
      img: "assets/pro-sourcing.jpg",
      alt: "A recruiter sourcing names at a bright desk.",
      lead: "Reaching top passive talent who are not on job boards.",
      who: "For teams and recruitment agencies that need executive search, leadership headhunting, and white-label sourcing for hard-to-fill senior roles.",
      includes: [
        "Executive search and leadership headhunting for hard-to-fill senior roles.",
        "LinkedIn Recruiter, advanced Boolean, and Google X-ray search.",
        "Competitor talent mapping, target-company research, and market benchmarking.",
        "Passive candidate outreach, identification, and initial screening.",
        "Qualified candidate shortlists and white-label sourcing support for recruitment agencies."
      ],
      how: [
        "You name the seat, market, and how the shortlist should be delivered.",
        "I run the sourcing sprint with regular progress updates — never silence.",
        "You receive a qualified shortlist you can work, under your brand if that is the agreed scope."
      ],
      receive: "Qualified shortlists, talent maps, and white-label sourcing as defined in the written scope."
    },
    "lead-generation": {
      title: "B2B Lead Generation",
      select: "B2B Lead Generation",
      img: "assets/pro-bd.jpg",
      alt: "A professional building a B2B pipeline.",
      lead: "Targeted decision-maker lists and outbound pipelines that open real conversations.",
      who: "For agencies, founders, and sales teams who need ICP research, verified contacts, and CRM-ready prospect lists.",
      includes: [
        "Ideal Customer Profile (ICP) and target-company research.",
        "Decision-maker identification and verified B2B contact list building (LinkedIn Sales Navigator, emails, and company intelligence).",
        "Lead qualification, industry research, and CRM-ready prospect lists.",
        "Recruitment client acquisition lists for staffing and search agencies.",
        "Sales pipeline building and outreach support where included in the agreed project scope."
      ],
      how: [
        "We agree your ICP, geography, and volume in writing.",
        "I research companies, identify decision-makers, and qualify each record.",
        "You receive a CRM-ready list — typically within 5 to 10 business days."
      ],
      receive: "Verified B2B contact lists qualified against your ICP. Meetings are not guaranteed; the work is a pipeline you can open."
    },
    "reverse-recruiting": {
      title: "Reverse Recruitment",
      select: "Reverse Recruitment",
      img: "assets/pro-interview.jpg",
      alt: "A private conversation about the next role.",
      lead: "I run the job search on your behalf — backed by 25+ placements in the US and UAE.",
      who: "For job seekers and professionals targeting US, UAE, UK, or global remote roles who want the search run with candidate approval at every step.",
      includes: [
        "CV and LinkedIn profile optimization.",
        "Target-company research and relevant job identification.",
        "Targeted job applications submitted with candidate approval.",
        "Direct recruiter and hiring manager outreach.",
        "Application tracking, follow-ups, and interview coordination support."
      ],
      how: [
        "You set the target roles, markets, and what must stay private.",
        "I research, apply with your approval, and reach hiring managers.",
        "You take the interviews. I stay on tracking, follow-ups, and coordination."
      ],
      receive: "A managed search file: applications, outreach, tracking, and interview support. Offers are never guaranteed."
    },
    "cv-career": {
      title: "CV Formatting & Optimization",
      select: "CV Formatting & Optimization",
      img: "assets/pro-desk.jpg",
      alt: "CVs and a notebook ready on a working desk.",
      lead: "Clean, ATS-conscious resumes structured for clarity and recruiter review.",
      who: "For global applicants who need a professional CV structured for ATS systems and recruiter review.",
      includes: [
        "Professional CV formatting and ATS-conscious structure.",
        "CV restructuring, keyword alignment, and improved visual presentation.",
        "Word, Google Docs, and PDF delivery.",
        "Cover letter formatting where required (delivered in 3 to 5 business days)."
      ],
      how: [
        "You send the current CV, target roles, and any must-keep facts.",
        "I restructure, align keywords, and return a clean draft.",
        "Final files arrive in Word, Google Docs, and PDF within 3 to 5 business days."
      ],
      receive: "Formatted CV files as agreed. Placement is a separate Reverse Recruitment or search engagement."
    }
  };

  const servicePage = document.getElementById("service-page");
  if (servicePage) {
    const id = new URLSearchParams(location.search).get("id") || "";
    const item = catalog[id];
    if (!item) {
      location.replace("index.html#services");
    } else {
      const photo = document.getElementById("svc-photo");
      const title = document.getElementById("svc-title");
      const lead = document.getElementById("svc-lead");
      const who = document.getElementById("svc-who");
      const includes = document.getElementById("svc-includes");
      const how = document.getElementById("svc-how");
      const receive = document.getElementById("svc-receive");
      const note = document.getElementById("svc-note");
      const others = document.getElementById("svc-others");
      const talk = document.getElementById("svc-talk");
      if (photo) {
        photo.src = item.img;
        photo.alt = item.alt;
      }
      if (title) title.textContent = item.title;
      document.title = item.title + " | Recruitexa";
      if (lead) lead.textContent = item.lead;
      if (who) who.textContent = item.who;
      if (includes) {
        includes.replaceChildren(...item.includes.map((line) => {
          const li = document.createElement("li");
          const strong = line.startsWith("<strong>") && line.endsWith("</strong>");
          li.textContent = strong ? line.replace(/<\/?strong>/g, "") : line;
          if (strong) li.className = "svc-strong";
          return li;
        }));
      }
      if (how) {
        how.replaceChildren(...item.how.map((line) => {
          const li = document.createElement("li");
          li.textContent = line;
          return li;
        }));
      }
      if (receive) receive.textContent = item.receive;
      if (note && item.note) {
        note.hidden = false;
        note.textContent = item.note;
      }
      if (talk) {
        talk.dataset.select = item.select;
        bindSelect(talk);
      }
      if (others) {
        others.replaceChildren(...Object.entries(catalog).filter(([key]) => key !== id).map(([key, svc]) => {
          const link = document.createElement("a");
          link.href = `service.html?id=${key}`;
          link.textContent = svc.title;
          return link;
        }));
      }
      const select = form && form.querySelector("[name='service']");
      if (select) select.value = item.select;
      try {
        sessionStorage.setItem("aroosa-select", item.select);
      } catch (err) {
        /* storage may be blocked */
      }
    }
  }

  const storedSelect = (() => {
    const fromQuery = new URLSearchParams(location.search).get("select");
    if (fromQuery) return fromQuery;
    try {
      return sessionStorage.getItem("aroosa-select");
    } catch (err) {
      return "";
    }
  })();
  if (form && storedSelect) {
    const select = form.querySelector("[name='service']");
    if (select && [...select.options].some((opt) => opt.value === storedSelect)) {
      select.value = storedSelect;
    }
  }

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

      const isCareer = form.dataset.intent === "career";
      const checks = [
        ["name", (value) => value.trim().length > 1, "Please add your name."],
        ["email", (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim()), "Please add an email that can be answered."],
        ["service", (value) => value.trim() !== "", isCareer ? "Please choose an area of interest." : "Please choose what you need."],
        ["message", (value) => value.trim().length > 8, isCareer ? "A few lines on your work are enough." : "A few lines are enough — what do you need?"]
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

      const cv = form.querySelector("[name='cv']");
      if (cv && cv.files && cv.files[0]) {
        const file = cv.files[0];
        const okType = /\.(pdf|docx?)$/i.test(file.name);
        const field = cv.closest(".field");
        if (!okType || file.size > 4 * 1024 * 1024) {
          if (field) field.classList.add("is-invalid");
          cv.setAttribute("aria-invalid", "true");
          problems.push("Please attach a PDF, DOC, or DOCX file under 4 MB.");
          if (!firstInvalid) firstInvalid = cv;
        }
      }

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

  const industryAcc = document.getElementById("industry-acc");
  const recordNote = document.getElementById("record-note");
  if (industryAcc && recordNote) {
    const items = [...industryAcc.querySelectorAll("details.industry-item")];
    const defaultNote = recordNote.getAttribute("data-default") || recordNote.textContent;
    const syncIndustryNote = () => {
      const open = items.find((item) => item.open);
      recordNote.textContent = (open && open.getAttribute("data-note")) || defaultNote;
    };
    items.forEach((item) => {
      const summary = item.querySelector("summary");
      if (!summary) return;
      summary.addEventListener("click", (event) => {
        event.preventDefault();
        const willOpen = !item.open;
        items.forEach((other) => {
          other.open = false;
        });
        item.open = willOpen;
        syncIndustryNote();
      });
    });
    syncIndustryNote();
  }
})();
