# Noble Finance Solutions — Modern Website

A responsive, accessible static website built with HTML, CSS, and JavaScript. No build tools or paid hosting required.

## Publish on GitHub Pages

1. Open your GitHub repository (`MosesNdovie/noble-finance-website`), and switch to the branch you want to publish.
2. Upload `index.html`, `styles.css`, and `script.js` into the **root** of that branch (not inside a subfolder). Commit the files.
3. Open **Settings → Pages**. Under **Build and deployment**, select **Deploy from a branch**.
4. Choose your branch (usually `main`) and the **/(root)** folder, then click **Save**.
5. Your website will be available at `https://mosesndovie.github.io/noble-finance-website/` after Pages publishes.

If you work on `development`, merge the changes into `main` before using `main` for Pages, or select `development` as the publishing branch.

## Before going live

- Confirm the official business name and obtain approval for the copy and branding.
- Contact details (phone (539) 237-7273, contact@noblefinancesolutions.com) come from the Noble Finance Solutions flyers. Booking buttons open a pre-filled email.
- Key-fact statistics in the 5 Risks section come from the flyer copy; confirm sources and update them each year.
- Update all claims, license disclosures, privacy information, and legal wording as required by the business and its regulators.
- Confirm that images, logos and brand assets you add are owned or licensed.
- To use the domain `noblefinancesolutions.com` with GitHub Pages, configure the custom domain in GitHub Pages **and** set DNS records with the domain registrar. Don't change DNS until you have authorization.

## Files

- `index.html`: site sections and content
- `styles.css`: visual design and responsive layout
- `script.js`: mobile navigation and copyright year
- `images/`: official Noble logo (light and dark versions), favicon, original service photos (not upscaled), advisor portrait, Safe Money Certified seal, BBB Accredited badges, the Health Check QR code and Maunty's contact-card QR

## Interactive features (all inside `index.html`)

- **5 Financial Risks explorer**: tabbed, auto-advancing panel with animated key-fact stats. Edit the `RISKS` array in the script.
- **Financial Protection Health Check**: 10-question quiz scored out of 100, with a per-risk breakdown. Runs entirely in the browser; the result pre-fills the booking email. Edit `QUESTIONS`, `ANSWERS` and `TIERS` in the script.
- **QR code** links to the existing checkup at https://hqr.la/1f159ada (taken from the flyer).
