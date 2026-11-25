/* Small enhancement layer: mobile access shortcuts, safe product art mapping and Telegram support. */
(() => {
  // Replace this with the project's official public support username/channel.
  const TELEGRAM_SUPPORT_URL = "https://t.me/ASICsMiningSupport";
  const svgTelegram = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M21.7 4.3 18.6 19c-.2 1- .8 1.2-1.6.7l-4.4-3.3-2.1 2c-.2.2-.4.4-.8.4l.3-4.5 8.2-7.4c.4-.3-.1-.5-.6-.2L7.5 13.1l-4.3-1.4c-.9-.3-.9-.9.2-1.3L20.2 3.8c.8-.3 1.6.2 1.5.5Z"/></svg>';
  const iconVip = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m12 2 2.8 6.1 6.7.7-5 4.5 1.4 6.6-5.9-3.4-5.9 3.4 1.4-6.6-5-4.5 6.7-.7L12 2Z"/></svg>';
  const productImages = ["/images/dr7.svg", "/images/ka3.svg", "/images/s21.svg", "/images/s23.svg", "/images/miner.svg", "/images/miner.svg"];

  function scrollToVip(event) {
    event.preventDefault();
    document.querySelector("#vip")?.scrollIntoView({ behavior: matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth" });
  }

  function install() {
    const root = document.querySelector("#app");
    if (!root) return;
    const isPublic = Boolean(root.querySelector(".public-header") && root.querySelector(".public-main"));
    const isDashboard = Boolean(root.querySelector(".app-shell"));

    if (isPublic) {
      const publicHeader = root.querySelector(".public-header");
      if (publicHeader && !publicHeader.querySelector(".refresh-header-vip")) {
        const vipLink = document.createElement("a");
        vipLink.className = "refresh-header-vip";
        vipLink.href = "#vip";
        vipLink.textContent = "VIP plans";
        vipLink.addEventListener("click", scrollToVip);
        publicHeader.append(vipLink);
      }

      const productSections = [root.querySelector("#products .public-product-grid"), root.querySelector("#vip .vip-plan-grid")].filter(Boolean);
      productSections.forEach((grid) => {
        [...grid.querySelectorAll("img")].forEach((image, index) => {
          const slot = productImages[index % productImages.length];
          if (slot && image.getAttribute("src")?.includes("/images/")) image.src = slot;
          image.loading = "lazy";
          image.decoding = "async";
          image.addEventListener("error", () => { image.hidden = true; }, { once: true });
        });
      });

      if (!root.querySelector(".refresh-mobile-actions")) {
        const dock = document.createElement("nav");
        dock.className = "refresh-mobile-actions";
        dock.setAttribute("aria-label", "Quick links");
        dock.innerHTML = `<a class="refresh-vip-link" href="#vip">${iconVip}<span>VIP plans</span></a><a class="refresh-telegram-link" href="${TELEGRAM_SUPPORT_URL}" target="_blank" rel="noopener noreferrer" aria-label="Open Telegram support">${svgTelegram}<span>Telegram support</span></a>`;
        dock.querySelector(".refresh-vip-link").addEventListener("click", scrollToVip);
        root.append(dock);
      }
    }

    if (isDashboard && !root.querySelector(".mobile-vip-trigger")) {
      const topbar = root.querySelector(".topbar");
      if (topbar) {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "mobile-vip-trigger";
        button.setAttribute("aria-label", "Open VIP membership");
        button.innerHTML = `${iconVip}<span>VIP</span>`;
        button.addEventListener("click", () => root.querySelector('[data-action="vip"]')?.click());
        topbar.append(button);
      }
    }

    if (!root.querySelector(".refresh-desktop-telegram") && !isPublic) {
      const link = document.createElement("a");
      link.className = "refresh-desktop-telegram";
      link.href = TELEGRAM_SUPPORT_URL;
      link.target = "_blank";
      link.rel = "noopener noreferrer";
      link.setAttribute("aria-label", "Open Telegram support");
      link.innerHTML = `${svgTelegram}<span>Telegram support</span>`;
      root.append(link);
    }
  }

  let scheduled = false;
  const observer = new MutationObserver(() => {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(() => { scheduled = false; install(); });
  });
  const start = () => {
    install();
    const root = document.querySelector("#app");
    if (root) observer.observe(root, { childList: true, subtree: true });
  };
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start, { once: true });
  else start();
})();
