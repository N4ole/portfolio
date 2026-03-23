const THEME_KEY = "theme-preference";
const toggleBtn = document.querySelector(".theme");

function getPreferredTheme() {
    const savedTheme = localStorage.getItem(THEME_KEY);
    if (savedTheme === "light" || savedTheme === "dark") return savedTheme;
    return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
}

function applyTheme(theme) {
    document.documentElement.setAttribute("data-theme", theme);
    if (toggleBtn) {
        toggleBtn.textContent = theme === "dark" ? "Mode clair" : "Mode sombre";
    }
}

function toggleTheme() {
    const current = document.documentElement.getAttribute("data-theme") || "light";
    const next = current === "dark" ? "light" : "dark";
    applyTheme(next);
    localStorage.setItem(THEME_KEY, next);
}

applyTheme(getPreferredTheme());

if (toggleBtn) {
    toggleBtn.addEventListener("click", toggleTheme);
}

const messageField = document.querySelector("#message");
const messageCounter = document.querySelector("#message-counter");

if (messageField && messageCounter) {
    const maxLength = Number(messageField.getAttribute("maxlength") || 1000);

    const updateCounter = () => {
        const currentLength = messageField.value.length;
        messageCounter.textContent = `${currentLength}/${maxLength} caractères`;
    };

    messageField.addEventListener("input", updateCounter);
    updateCounter();
}