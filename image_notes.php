<?php
session_start();

// 🔴 CHANGE THIS SESSION NAME TO MATCH YOUR SYSTEM IF NEEDED
if (!isset($_SESSION['username']) && !isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

include 'db_connect.php';

/* ===================== IMAGE NOTES ===================== */

// DELETE IMAGE NOTE
if (isset($_GET['delete_img'])) {
    $id = (int)$_GET['delete_img'];
    $q = mysqli_query($conn, "SELECT image_path FROM image_notes WHERE id=$id");
    $row = mysqli_fetch_assoc($q);
    if ($row) {
        if (file_exists($row['image_path'])) {
            unlink($row['image_path']);
        }
        mysqli_query($conn, "DELETE FROM image_notes WHERE id=$id");
    }
    header("Location: image_notes.php");
    exit();
}

// ADD IMAGE NOTE
if (isset($_POST['save_image'])) {

    $date = $_POST['note_date'];
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);

    $img = $_FILES['image']['name'];
    $tmp = $_FILES['image']['tmp_name'];

    if ($img != "") {
        $ext = pathinfo($img, PATHINFO_EXTENSION);
        $newName = "note_" . time() . "_" . rand(1000,9999) . "." . $ext;
        $path = "uploads/notes/" . $newName;

        if (move_uploaded_file($tmp, $path)) {
            mysqli_query($conn, "INSERT INTO image_notes (note_date, title, description, image_path)
            VALUES ('$date','$title','$desc','$path')");

            header("Location: image_notes.php?tab=images&saved=1");
            exit();
        }
    }
}


/* ===================== TEXT NOTES ===================== */

// DELETE TEXT NOTE
if (isset($_GET['delete_text'])) {
    $id = (int)$_GET['delete_text'];
    mysqli_query($conn, "DELETE FROM text_notes WHERE id=$id");
    header("Location: image_notes.php");
    exit();
}

// ADD TEXT NOTE
if (isset($_POST['save_text'])) {

    $d = $_POST['t_date'];
    $t = mysqli_real_escape_string($conn, $_POST['t_title']);
    $n = mysqli_real_escape_string($conn, $_POST['t_note']);

    // Check duplicate
    $check = mysqli_query($conn, "SELECT id FROM text_notes WHERE note_date='$d' AND title='$t'");

    if (mysqli_num_rows($check) == 0) {
        mysqli_query($conn, "INSERT INTO text_notes (note_date,title,note) VALUES ('$d','$t','$n')");
        header("Location: image_notes.php?tab=notes&saved=1");
        exit();
    } else {
        header("Location: image_notes.php?tab=notes&exists=1");
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Image & Notes</title>
<style>
* {
    box-sizing: border-box;
}

body {
    font-family: "Segoe UI", Tahoma, Arial, sans-serif;
    background: #f2f4f8;
    margin: 0;
    padding: 20px;
    color: #333;
}

h2 {
    margin-bottom: 15px;
}

/* Tabs */
.tabbar {
    margin-bottom: 20px;
}

.tabbtn {
    border: none;
    background: #e0e0e0;
    padding: 10px 20px;
    margin-right: 10px;
    font-size: 16px;
    border-radius: 6px;
    cursor: pointer;
    transition: 0.2s;
}

.tabbtn:hover {
    background: #d0d0d0;
}

.tabbtn.active {
    background: #2b7cff;
    color: #fff;
}

/* Boxes */
.box {
    background: #fff;
    padding: 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

/* Form */
label {
    font-weight: 600;
    display: block;
    margin-top: 10px;
}

input, textarea {
    width: 100%;
    padding: 10px;
    margin-top: 5px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 15px;
}

input:focus, textarea:focus {
    outline: none;
    border-color: #2b7cff;
}

/* Buttons */
button {
    margin-top: 12px;
    padding: 10px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    background: #2b7cff;
    color: #fff;
    font-size: 15px;
}

button:hover {
    background: #1f66d1;
}

/* Image grid */
.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 16px;
}

.card {
    background: #fff;
    padding: 10px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: 0.2s;
}

.card:hover {
    transform: translateY(-2px);
}

.card img {
    width: 100%;
    height: 200px;
    object-fit: cover;
    border-radius: 6px;
    cursor: pointer;
}

.card h4 {
    margin: 8px 0 4px 0;
}

.card small {
    color: #666;
}

/* Delete link */
.del {
    display: inline-block;
    margin-top: 6px;
    color: #e53935;
    font-weight: bold;
    text-decoration: none;
}

.del:hover {
    text-decoration: underline;
}

/* Table (Excel style) */
table {
    width: 100%;
    background: #fff;
    border-collapse: collapse;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    border-radius: 10px;
    overflow: hidden;
}

th {
    background: #2b7cff;
    color: #fff;
    padding: 10px;
    text-align: left;
}

td {
    padding: 10px;
    border-bottom: 1px solid #eee;
}

tr:hover td {
    background: #f6f9ff;
}

.nav{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  background:#1e88e5;
  padding:10px 14px;
  justify-content:center;
}

.nav a{
  color:#fff;
  padding:5px 10px;
  text-decoration:none;
  background:#0f63a3;
  border-radius:6px;
  font-weight:600;
  font-size:13px;
  font-family: 'Merriweather', serif;
  letter-spacing: 0.3px;
}

.nav a:hover{
  background:#0b4f85;
  transform:translateY(-1px);
}

/* Logout button */
.nav a[style]{
  background:#d32f2f !important;
}
.nav a[style]:hover{
  background:#b71c1c !important;
}
</style>
</head>
<body>

<div class="nav">
  <a href="insaf_home.php">Dashboard</a>
  <a href="quick_entry.php">Addition</a>
 
  <a href="daily_parchoon.php">Parchoon</a>
  <a href="sale_record.php">Sale Record</a>
  <a href="stock_records.php">Stock Record</a>
  <a href="expenditure.php">Expenditure</a>
  <a href="daily_guaranty.php">Guaranty</a>
  <a href="daily_closing.php">Daily Sumary</a>
  <?php if($_SESSION['role']==='md'): ?><a href="cash_in_hand.php">Cash in Hand</a><?php endif; ?>
  <?php if($_SESSION['role']==='md'): ?><a href="profit.php">Profit</a><?php endif; ?>
  <?php if($_SESSION['role']==='md'): ?><a href="assets.php">Assets</a><?php endif; ?>
  
  
  <a href="checklist.php">Check List</a>
  
</div>


<?php
if (isset($_GET['saved'])) echo "<script>alert('Saved successfully');</script>";
if (isset($_GET['exists'])) echo "<script>alert('This note already exists');</script>";
?>

<h2>📂 Proof / Image Notes & Text Notes</h2>

<!-- TABS -->
<div class="tabbar">
    <button class="tabbtn active" id="btnImages" onclick="showTab('images')">📸 Image Notes</button>
    <button class="tabbtn" id="btnNotes" onclick="showTab('notes')">📝 Text Notes</button>
</div>


<!-- ================= IMAGE NOTES ================= -->
<div id="images">

<div class="box">
<form method="post" enctype="multipart/form-data">
    <label>Date</label>
    <input type="date" name="note_date" value="<?php echo date('Y-m-d'); ?>" required>

    <label>Title</label>
    <input type="text" name="title" required>

    <label>Description</label>
    <textarea name="description" rows="4"></textarea>

    <label>Image</label>
    <input type="file" name="image" required accept="image/*">

    <button type="submit" name="save_image">💾 Save Image Note</button>
</form>
</div>

<div class="grid">
<?php
$res = mysqli_query($conn, "SELECT * FROM image_notes ORDER BY id DESC");
while ($row = mysqli_fetch_assoc($res)) {
?>
    <div class="card">
        <img src="<?php echo $row['image_path']; ?>" onclick="window.open(this.src)">
        <h4><?php echo htmlspecialchars($row['title']); ?></h4>
        <small><?php echo $row['note_date']; ?></small>
        <p><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>
        <a class="del" href="?delete_img=<?php echo $row['id']; ?>" onclick="return confirm('Delete this image note?')">🗑 Delete</a>
    </div>
<?php } ?>
</div>

</div>

<!-- ================= TEXT NOTES ================= -->
<div id="notes" style="display:none">

<div class="box">
<form method="post">
    <label>Date</label>
    <input type="date" name="t_date" value="<?php echo date('Y-m-d'); ?>">

    <label>Title</label>
    <input type="text" name="t_title">

    <label>Note</label>
    <textarea name="t_note" rows="4"></textarea>

    <button type="submit" name="save_text" onclick="this.disabled=true; this.form.submit();">
💾 Save Text Note
</button>

</form>
</div>

<table border="1" width="100%">
<tr>
    <th>Date</th>
    <th>Title</th>
    <th>Note</th>
    <th>Action</th>
</tr>

<?php
$r = mysqli_query($conn, "SELECT * FROM text_notes ORDER BY id DESC");
while ($x = mysqli_fetch_assoc($r)) {
?>
<tr>
    <td><?php echo $x['note_date']; ?></td>
    <td><?php echo htmlspecialchars($x['title']); ?></td>
    <td><?php echo nl2br(htmlspecialchars($x['note'])); ?></td>
    <td>
        <a class="del" href="?delete_text=<?php echo $x['id']; ?>" onclick="return confirm('Delete this note?')">🗑 Delete</a>
    </td>
</tr>
<?php } ?>

</table>

</div>

<script>
function showTab(tab) {
    document.getElementById('images').style.display = (tab === 'images') ? 'block' : 'none';
    document.getElementById('notes').style.display = (tab === 'notes') ? 'block' : 'none';

    document.getElementById('btnImages').classList.remove('active');
    document.getElementById('btnNotes').classList.remove('active');

    if (tab === 'images') {
        document.getElementById('btnImages').classList.add('active');
    } else {
        document.getElementById('btnNotes').classList.add('active');
    }
}
</script>


</body>
</html>
