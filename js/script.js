/* === THEME === */
const THEME_KEY = "theme-preference";
const toggleBtn = document.querySelector(".theme");

function getPreferredTheme() {
    const saved = localStorage.getItem(THEME_KEY);
    if (saved === "light" || saved === "dark") return saved;
    return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
}

function applyTheme(theme) {
    document.documentElement.setAttribute("data-theme", theme);
    if (toggleBtn) {
        toggleBtn.textContent = theme === "dark" ? "Mode clair" : "Mode sombre";
        toggleBtn.setAttribute("aria-pressed", theme === "dark");
    }
}

function toggleTheme() {
    const current = document.documentElement.getAttribute("data-theme") || "light";
    const next = current === "dark" ? "light" : "dark";
    applyTheme(next);
    localStorage.setItem(THEME_KEY, next);
}

applyTheme(getPreferredTheme());
if (toggleBtn) toggleBtn.addEventListener("click", toggleTheme);

/* === SCROLL REVEAL === */
const revealObserver = new IntersectionObserver(
    (entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add("visible");
                revealObserver.unobserve(entry.target);
            }
        });
    },
    { threshold: 0.12, rootMargin: "0px 0px -40px 0px" }
);

document.querySelectorAll(".reveal").forEach((el) => revealObserver.observe(el));

/* === MESSAGE CHAR COUNTER === */
const messageField = document.querySelector("#message");
const messageCounter = document.querySelector("#message-counter");

if (messageField && messageCounter) {
    const maxLength = Number(messageField.getAttribute("maxlength") || 1000);

    const updateCounter = () => {
        const len = messageField.value.length;
        messageCounter.textContent = `${len}/${maxLength} caractères`;
    };

    messageField.addEventListener("input", updateCounter);
    updateCounter();
}
