<?php

require_once __DIR__ . '/functions.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $name     = trim($_POST['name'] ?? '');
        $url      = trim($_POST['url'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $id       = (int)($_POST['id'] ?? 0);

        if ($name && $url) {
            save_feed($name, $url, $category, $id);
            $message = $id ? 'Feed updated.' : 'Feed added.';
        }
    } elseif ($action === 'toggle') {
        toggle_feed((int)$_POST['id']);
        $message = 'Feed status updated.';
    } elseif ($action === 'delete') {
        delete_feed((int)$_POST['id']);
        $message = 'Feed deleted.';
    }
}

$feeds     = get_feeds();
$edit_feed = isset($_GET['edit']) ? get_feed((int)$_GET['edit']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Moodwire — Feeds</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
  <nav class="nav">
    <a href="index.php" class="nav-logo">Moodwire</a>
    <a href="feeds.php" class="active">Feeds</a>
    <a href="run.php">Run Pipeline</a>
  </nav>

  <h1>RSS Feeds</h1>

  <?php if ($message): ?>
    <div class="message"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <div class="card">
    <h2><?= $edit_feed ? 'Edit Feed' : 'Add Feed' ?></h2>
    <form method="POST">
      <input type="hidden" name="action" value="save">
      <?php if ($edit_feed): ?>
        <input type="hidden" name="id" value="<?= $edit_feed['id'] ?>">
      <?php endif; ?>
      <div class="form-row">
        <input type="text" name="name" placeholder="Feed name" required
               value="<?= htmlspecialchars($edit_feed['name'] ?? '') ?>">
        <input type="url" name="url" placeholder="RSS URL" required
               value="<?= htmlspecialchars($edit_feed['url'] ?? '') ?>">
        <input type="text" name="category" placeholder="Category (optional)"
               value="<?= htmlspecialchars($edit_feed['category'] ?? '') ?>">
        <button type="submit" class="btn"><?= $edit_feed ? 'Update' : 'Add Feed' ?></button>
      </div>
    </form>
  </div>

  <table class="table">
    <thead>
      <tr>
        <th>Name</th><th>URL</th><th>Category</th>
        <th>Last Fetched</th><th>Status</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($feeds as $feed): ?>
      <tr class="<?= $feed['active'] ? '' : 'inactive' ?>">
        <td><?= htmlspecialchars($feed['name']) ?></td>
        <td><a href="<?= htmlspecialchars($feed['url']) ?>" target="_blank" class="url-cell">
            <?= htmlspecialchars($feed['url']) ?></a></td>
        <td><?= htmlspecialchars($feed['category'] ?? '—') ?></td>
        <td><?= $feed['last_fetched'] ?? 'Never' ?></td>
        <td><span class="badge <?= $feed['active'] ? 'badge-green' : 'badge-grey' ?>">
            <?= $feed['active'] ? 'Active' : 'Paused' ?></span></td>
        <td class="actions">
          <a href="?edit=<?= $feed['id'] ?>" class="btn btn-sm">Edit</a>
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= $feed['id'] ?>">
            <button class="btn btn-sm btn-grey"><?= $feed['active'] ? 'Pause' : 'Activate' ?></button>
          </form>
          <form method="POST" style="display:inline"
                onsubmit="return confirm('Delete this feed and all its articles?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $feed['id'] ?>">
            <button class="btn btn-sm btn-red">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
</body>
</html>
