# Requirements Document

## Introduction

PetMate is a PHP/MySQL veterinary management platform running on XAMPP. This feature covers a full UI redesign that unifies the visual language across the entire application — from the public-facing landing page through every role-specific dashboard. The redesign introduces a dark sidebar shell (`#1A2E2B`), a refined landing page, and a structured multi-role dashboard architecture with dedicated subfolders and PHP files per role. All pages continue to use the existing warm PetMate design tokens (cream, espresso, blush, terracotta) and the Playfair Display + Inter typeface pairing already loaded in the project.

---

## Glossary

- **System**: The PetMate PHP/MySQL web application running on XAMPP.
- **Landing_Page**: The public `index.php` file served to unauthenticated visitors.
- **Navbar**: The fixed top navigation bar rendered on the Landing_Page.
- **Sidebar**: The fixed 240 px left-side navigation panel rendered inside authenticated dashboard pages via `includes/header.php`.
- **Topbar**: The 60 px fixed top bar rendered inside authenticated dashboard pages, positioned to the right of the Sidebar.
- **Layout_Shell**: The combination of Sidebar + Topbar + content wrapper produced by `includes/header.php` and closed by `includes/footer.php`.
- **Dashboard_Page**: Any PHP file inside a role subfolder under `dashboards/` that renders within the Layout_Shell.
- **Stat_Card**: A compact summary card displaying a single numeric metric with a label and optional icon.
- **Role**: One of `pet_owner`, `csr`, `vet` (veterinarian tech), `vet_technician`, or `vet_assistant` — the value stored in `$_SESSION['role']`.
- **Active_Nav_Item**: The sidebar navigation link whose `href` matches the current page URI.
- **Design_Token**: A CSS custom property defined in `assets/css/style.css` (e.g., `--color-espresso`, `--color-accent`).
- **Marquee_Ticker**: A horizontally scrolling strip of feature highlights displayed below the hero CTA buttons.
- **Blush**: The warm pink-beige tones defined by `--color-blush-light`, `--color-blush-mid`, and `--color-blush-deep`.
- **Espresso**: The dark brown color `#3D2B1F` defined by `--color-espresso`.
- **Dark_Teal**: The sidebar background color `#1A2E2B`.
- **Boxicons**: The icon library already loaded via CDN (`https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css`).
- **requireRole**: The PHP function in `includes/auth.php` that enforces role-based access and redirects on mismatch.
- **require_permission**: The PHP function in `includes/rbac.php` that enforces permission-based access.
- **PDO**: The `$pdo` database connection object available after `require_once '../includes/db.php'`.

---

## Requirements

### Requirement 1: Landing Page — Navbar

**User Story:** As a visitor, I want a clear and attractive top navigation bar, so that I can easily find login and registration links and understand the site's identity.

#### Acceptance Criteria

1. THE Navbar SHALL display the PetMate paw logo icon and "PetMate" wordmark left-aligned, using Playfair Display font and `--color-espresso` color.
2. THE Navbar SHALL display centered navigation links: Home, About, Services, and Contact.
3. THE Navbar SHALL display a pill-outline "Log In" button and a pill-filled "Register" button right-aligned; the "Log In" button SHALL link to `login.php` and the "Register" button SHALL link to `register.php`.
4. WHEN the page scroll position exceeds 20 px, THE Navbar SHALL transition its background from transparent to `#FFFFFF` and add a subtle box shadow.
5. WHILE the Navbar background is transparent, THE Navbar SHALL render navigation link text in `--color-text` so it remains legible against the cream hero background.
6. THE Navbar SHALL remain fixed at the top of the viewport at all times (position: fixed, z-index above page content).

---

### Requirement 2: Landing Page — Hero Section

**User Story:** As a visitor, I want an engaging hero section, so that I immediately understand PetMate's value proposition and can take action.

#### Acceptance Criteria

1. THE Landing_Page SHALL render a full-viewport-height hero section with `--color-bg` (`#FAF0E6`) as the background.
2. THE Landing_Page SHALL display a Playfair Display headline of at least 40 px (scaling up to 72 px via `clamp`) with an italic accent phrase colored `--color-accent`.
3. THE Landing_Page SHALL display a subtitle paragraph in Inter font, `--color-muted` color, with a maximum width of 480 px.
4. THE Landing_Page SHALL display two CTA buttons below the subtitle: a primary "Get Started" button linking to `register.php` and an outline "Learn More" button that smooth-scrolls to the `#about` section.
5. WHEN the "Learn More" button is clicked, THE Landing_Page SHALL smooth-scroll the viewport to the About section without a full page reload.
6. THE Landing_Page SHALL render a Marquee_Ticker strip immediately below the CTA buttons, using `--color-blush-mid` background, with at least 8 feature highlight items that scroll continuously.
7. WHEN the user hovers over the Marquee_Ticker, THE Marquee_Ticker SHALL pause its scrolling animation.

---

### Requirement 3: Landing Page — About Section

**User Story:** As a visitor, I want an informative about section, so that I can learn what PetMate offers before registering.

#### Acceptance Criteria

1. THE Landing_Page SHALL render a two-column About section: left column contains a blush-gradient image placeholder with a centered paw icon overlay; right column contains an eyebrow label, heading, paragraph, feature list, and a CTA button.
2. THE Landing_Page SHALL display at least three feature highlight items in the right column, each with a blush-background icon, a bold title, and a short description.
3. THE Landing_Page SHALL display a "Discover More" or "Get Started Today" CTA button in the right column that links to `register.php`.
4. WHEN the viewport width is 900 px or less, THE Landing_Page SHALL collapse the two-column About layout into a single-column stacked layout.

---

### Requirement 4: Landing Page — Footer

**User Story:** As a visitor, I want a complete footer, so that I can find quick links, contact information, and social media links.

#### Acceptance Criteria

1. THE Landing_Page SHALL render a footer with `--color-espresso` (`#3D2B1F`) background.
2. THE Landing_Page footer SHALL display a four-column grid: brand column (logo + tagline + social icons), Quick Links column, Services column, and Contact column.
3. THE Landing_Page footer SHALL display social icon buttons for Facebook, Instagram, Twitter, and LinkedIn using Boxicons (`bxl-facebook`, `bxl-instagram`, `bxl-twitter`, `bxl-linkedin`).
4. WHEN a social icon button is hovered, THE Landing_Page footer SHALL change the button background to `--color-accent` and the icon color to `--color-bg`.
5. THE Landing_Page footer SHALL display a bottom bar with copyright text (`© [current year] PetMate. All rights reserved.`) and links to Privacy Policy, Terms of Service, and Cookie Policy.
6. WHEN the viewport width is 900 px or less, THE Landing_Page footer SHALL collapse the four-column grid to a two-column layout; WHEN the viewport width is 560 px or less, THE Landing_Page footer SHALL collapse to a single-column layout.

---

### Requirement 5: Sidebar Redesign

**User Story:** As an authenticated user, I want a clear and role-aware sidebar, so that I can navigate to all pages relevant to my role quickly.

#### Acceptance Criteria

1. THE Sidebar SHALL be fixed-position, 240 px wide, full viewport height, with `#1A2E2B` (Dark_Teal) background.
2. THE Sidebar SHALL display the PetMate paw icon (`bx-paw`) and "PetMate" wordmark at the top, followed by a role badge pill showing the current user's role label.
3. THE Sidebar SHALL display a horizontal divider line below the brand/role area.
4. THE Sidebar SHALL render navigation links appropriate to the current user's Role, each link displaying a Boxicons icon and a text label.
5. WHEN a navigation link corresponds to the Active_Nav_Item, THE Sidebar SHALL render that link with a teal background, white text, and a 3 px left accent bar in `--color-accent`.
6. THE Sidebar SHALL display a user section at the bottom containing a circular avatar with the user's initials, the user's name, and a logout button linking to `logout.php`.
7. WHEN the logout button is clicked, THE System SHALL redirect the user to `logout.php`.
8. THE Sidebar navigation links for each Role SHALL be:
   - `pet_owner`: Dashboard, My Pets, Book Sitter, My Bookings, Messages, Bills, Visit Records, Settings
   - `csr`: Dashboard, Pet Info, Pet Records, Billing, Visit Summaries, Messages, Settings
   - `vet`: Dashboard, Exam Room, Assessments, Treatment Plan, Prescriptions, Settings
   - `vet_technician`: Dashboard, Assessment Queue, Record Results, Treatment Details, Settings
   - `vet_assistant`: Dashboard, Prepare Room, Instructions, Discharge Prep, Administer, Discharge, Settings

---

### Requirement 6: Topbar

**User Story:** As an authenticated user, I want a persistent top bar showing context, so that I always know which page I am on and can access notifications and my profile.

#### Acceptance Criteria

1. THE Topbar SHALL be 60 px tall, white background (`--color-surface`), and span the full width of the viewport minus the Sidebar width (240 px).
2. THE Topbar SHALL display the current page title on the left side using Playfair Display font.
3. THE Topbar SHALL display a notification bell icon (`bx-bell`), a circular user avatar with initials, and a role pill badge on the right side.
4. THE Topbar SHALL remain sticky at the top of the main content area while the user scrolls the page content.
5. WHEN the notification bell icon is hovered, THE Topbar SHALL change the bell icon color to `--color-accent`.

---

### Requirement 7: Shared Layout Shell

**User Story:** As a developer, I want a single shared layout shell, so that all dashboard pages render consistently without duplicating HTML structure.

#### Acceptance Criteria

1. THE Layout_Shell SHALL be produced by `includes/header.php` (opening HTML, Sidebar, Topbar, and opening content wrapper) and closed by `includes/footer.php` (closing content wrapper, main, app-container, script tag, and closing HTML).
2. THE Layout_Shell SHALL conditionally render the Sidebar and Topbar only when `$_SESSION['user_id']` is set; unauthenticated pages SHALL render with `full-width` class on `.main-content`.
3. THE Layout_Shell SHALL load `assets/css/style.css`, Playfair Display + Inter from Google Fonts, and Boxicons from CDN in the `<head>`.
4. THE Layout_Shell SHALL expose the `$pdo` database connection to all Dashboard_Pages via `require_once '../includes/db.php'` called before `require_once '../includes/header.php'`.
5. WHEN `includes/header.php` is included, THE System SHALL make `$role`, `$userName`, `$initials`, and `$roleLabel` PHP variables available for use in the page body.

---

### Requirement 8: Pet Owner Dashboard Pages

**User Story:** As a pet owner, I want a dedicated set of dashboard pages, so that I can manage my pets, bookings, bills, messages, and visit records from a single interface.

#### Acceptance Criteria

1. THE System SHALL provide the following PHP files under `dashboards/pet_owner/`: `index.php`, `my_pets.php`, `book_sitter.php`, `my_bookings.php`, `messages.php`, `bills.php`, `visit_records.php`, `settings.php`.
2. WHEN any `dashboards/pet_owner/` page is loaded, THE System SHALL call `requireRole('pet_owner')` and `require_permission('view_dashboard')` before rendering content.
3. THE `dashboards/pet_owner/index.php` page SHALL display Stat_Cards for: total registered pets, active bookings, outstanding bill amount, and unread messages.
4. THE `dashboards/pet_owner/my_pets.php` page SHALL display a list of the owner's pets with options to add, edit, and view each pet's profile.
5. THE `dashboards/pet_owner/bills.php` page SHALL display a table of bills with columns for date, pet name, amount, status, and a pay action for unpaid bills.
6. THE `dashboards/pet_owner/visit_records.php` page SHALL display the full visit history for all of the owner's pets.
7. THE `dashboards/pet_owner/settings.php` page SHALL display a form for updating the user's profile information and changing their password.

---

### Requirement 9: Client Service Representative Dashboard Pages

**User Story:** As a CSR, I want a dedicated set of dashboard pages, so that I can validate pet information, manage records, compute billing, and communicate with pet owners.

#### Acceptance Criteria

1. THE System SHALL provide the following PHP files under `dashboards/csr/`: `index.php`, `pet_info.php`, `pet_records.php`, `billing.php`, `visit_summaries.php`, `messages.php`, `settings.php`.
2. WHEN any `dashboards/csr/` page is loaded, THE System SHALL call `requireRole('csr')` and `require_permission('view_dashboard')` before rendering content.
3. THE `dashboards/csr/index.php` page SHALL display Stat_Cards for: pending validations count, new records count, and billing queue count.
4. THE `dashboards/csr/pet_info.php` page SHALL display a form or list for receiving and validating initial pet information submitted by pet owners.
5. THE `dashboards/csr/billing.php` page SHALL display a form for computing fees and generating bills for completed visits.

---

### Requirement 10: Veterinarian Tech Dashboard Pages

**User Story:** As a veterinarian tech, I want a dedicated set of dashboard pages, so that I can manage examinations, assessments, treatment plans, and prescriptions.

#### Acceptance Criteria

1. THE System SHALL provide the following PHP files under `dashboards/vet/`: `index.php`, `exam_room.php`, `assessments.php`, `treatment_plan.php`, `prescriptions.php`, `settings.php`.
2. WHEN any `dashboards/vet/` page is loaded, THE System SHALL call `requireRole('vet')` and `require_permission('view_dashboard')` before rendering content.
3. THE `dashboards/vet/index.php` page SHALL display Stat_Cards for: today's exams count, pending assessments count, and active treatments count.
4. THE `dashboards/vet/assessments.php` page SHALL display a list of pets requiring assessment with the ability to record findings and order tests.
5. THE `dashboards/vet/treatment_plan.php` page SHALL display a form for creating and presenting a treatment plan for a selected pet.
6. THE `dashboards/vet/prescriptions.php` page SHALL display a form for issuing prescriptions linked to a treatment plan.

---

### Requirement 11: Vet Technician Dashboard Pages

**User Story:** As a vet technician, I want a dedicated set of dashboard pages, so that I can view assessment requests, record test results, and review treatment details.

#### Acceptance Criteria

1. THE System SHALL provide the following PHP files under `dashboards/vet_technician/`: `index.php`, `assessment_queue.php`, `record_results.php`, `treatment_details.php`, `settings.php`.
2. WHEN any `dashboards/vet_technician/` page is loaded, THE System SHALL call `requireRole('vet_technician')` and `require_permission('view_dashboard')` before rendering content.
3. THE `dashboards/vet_technician/index.php` page SHALL display Stat_Cards for: assessment queue count and pending results count.
4. THE `dashboards/vet_technician/assessment_queue.php` page SHALL display a table of incoming assessment test requests with pet name, test type, and requested date.
5. THE `dashboards/vet_technician/record_results.php` page SHALL display a form for entering and submitting assessment data for a selected test request.

---

### Requirement 12: Vet Assistant Dashboard Pages

**User Story:** As a vet assistant, I want a dedicated set of dashboard pages, so that I can prepare examination rooms, follow medical instructions, administer treatments, and assist with discharge.

#### Acceptance Criteria

1. THE System SHALL provide the following PHP files under `dashboards/vet_assistant/`: `index.php`, `prepare_room.php`, `instructions.php`, `discharge_prep.php`, `settings.php`, `administer.php`, `discharge.php`.
2. WHEN any `dashboards/vet_assistant/` page is loaded, THE System SHALL call `requireRole('vet_assistant')` and `require_permission('view_dashboard')` before rendering content.
3. THE `dashboards/vet_assistant/index.php` page SHALL display Stat_Cards for: rooms to prepare count, active treatments count, and discharge queue count.
4. THE `dashboards/vet_assistant/prepare_room.php` page SHALL display a form for submitting room preparation details for a selected patient.
5. THE `dashboards/vet_assistant/instructions.php` page SHALL display medical instructions relayed from the veterinarian tech for the current active patients.
6. THE `dashboards/vet_assistant/administer.php` page SHALL display a form for recording treatment administration details.
7. THE `dashboards/vet_assistant/discharge.php` page SHALL display a form for preparing a patient for discharge.

---

### Requirement 13: CSS Design Token Updates

**User Story:** As a developer, I want the CSS design tokens and sidebar styles updated to use the new Dark_Teal color, so that the sidebar visually matches the redesign specification.

#### Acceptance Criteria

1. THE `assets/css/style.css` file SHALL define a `--color-sidebar` CSS custom property with value `#1A2E2B`.
2. THE `.sidebar` CSS rule SHALL use `--color-sidebar` as its `background-color` instead of `--color-espresso`.
3. THE `.sidebar-nav li a.active` CSS rule SHALL render with a teal-tinted background, white text, and a 3 px left border in `--color-accent`.
4. WHEN the `.sidebar-nav li a.active` style is applied, THE Sidebar SHALL visually distinguish the active link from inactive links without relying solely on color (the 3 px left accent bar provides a non-color indicator for accessibility).
5. THE `assets/css/style.css` file SHALL preserve all existing Design_Tokens (`--color-bg`, `--color-espresso`, `--color-accent`, `--color-surface`, `--color-border`, and all others) without modification.

---

### Requirement 14: Role-Aware Redirect Updates

**User Story:** As an authenticated user, I want the system to redirect me to my new role-specific dashboard subfolder, so that I land on the correct index page after login.

#### Acceptance Criteria

1. WHEN `redirectBasedOnRole('pet_owner')` is called, THE System SHALL redirect to `/Petmate/dashboards/pet_owner/index.php`.
2. WHEN `redirectBasedOnRole('csr')` is called, THE System SHALL redirect to `/Petmate/dashboards/csr/index.php`.
3. WHEN `redirectBasedOnRole('vet')` is called, THE System SHALL redirect to `/Petmate/dashboards/vet/index.php`.
4. WHEN `redirectBasedOnRole('vet_technician')` is called, THE System SHALL redirect to `/Petmate/dashboards/vet_technician/index.php`.
5. WHEN `redirectBasedOnRole('vet_assistant')` is called, THE System SHALL redirect to `/Petmate/dashboards/vet_assistant/index.php`.
6. WHEN `redirectBasedOnRole('admin')` is called, THE System SHALL redirect to `/Petmate/dashboards/admin.php` (unchanged).

---

### Requirement 15: Accessibility and Responsive Behavior

**User Story:** As a user on any device, I want the interface to be usable and accessible, so that I can interact with PetMate regardless of screen size or assistive technology.

#### Acceptance Criteria

1. THE Navbar SHALL include `aria-label` attributes on icon-only buttons and links.
2. THE Sidebar navigation links SHALL be keyboard-navigable in logical tab order.
3. THE Active_Nav_Item SHALL be distinguishable from inactive items by both color and a visible non-color indicator (the 3 px left accent bar).
4. WHEN the viewport width is 640 px or less, THE Sidebar SHALL be hidden off-screen (transform: translateX(-100%)) and THE main content SHALL expand to full width.
5. WHEN the viewport width is 900 px or less, THE Landing_Page grid layouts (About section, Footer) SHALL collapse to fewer columns as specified in Requirements 3 and 4.
6. THE Landing_Page footer social icon buttons SHALL include `aria-label` attributes describing each social network.
