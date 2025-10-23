<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Beranda</title>
    <style>
      :root {
        --bg: #f7fafc;
        --fg: #111827;
        --primary: #2563eb;
        --primary-contrast: #ffffff;
      }
      * { box-sizing: border-box; }
      body {
        margin: 0;
        font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
        background: var(--bg);
        color: var(--fg);
        line-height: 1.5;
      }
      header, main {
        display: flex;
        align-items: center;
        justify-content: center;
      }
      header {
        padding: 1rem;
      }
      main {
        min-height: calc(100dvh - 80px);
        padding: 2rem;
      }
      .container {
        width: 100%;
        max-width: 560px;
        text-align: center;
      }
      h1 {
        margin: 0 0 0.5rem 0;
        font-size: 1.875rem;
      }
      p {
        margin: 0 0 1.5rem 0;
        color: #374151;
      }
      .btn {
        display: inline-block;
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;
        background: var(--primary);
        color: var(--primary-contrast);
        text-decoration: none;
        font-weight: 600;
        border: 0;
        cursor: pointer;
      }
      .btn:focus, .btn:hover {
        filter: brightness(0.95);
        outline: 2px solid #93c5fd;
        outline-offset: 2px;
      }
    </style>
  </head>
  <body>
    <header aria-label="Header">
      <strong>Contoh Aplikasi</strong>
    </header>
    <main>
      <div class="container">
        <h1>Selamat datang</h1>
        <p>Silakan masuk untuk melanjutkan.</p>
        <a
          href="login.php"
          class="btn"
          role="button"
          aria-label="Masuk ke halaman login"
        >Masuk</a>
      </div>
    </main>
  </body>
</html>
