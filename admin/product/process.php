<?php require_once $_SERVER["DOCUMENT_ROOT"] . "/admin/include/connect.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/admin/include/protect.php";

function generateFileName($str, $ext)
{
    $result = $str;
    $result = strtolower($result);
    $pattern = array(' ', 'é', 'è', 'ë', 'ê', 'á', 'à', 'ä', 'â', 'å', 'ã', 'ó', 'ò', 'ö', 'ô', 'õ', 'í', 'ì', 'ï', 'ú', 'ù', 'ü', 'û', 'ý', 'ÿ', 'ø', 'œ', 'ç', 'ñ', 'ß', 'ț', 'ș', 'ř', 'ž', 'á', 'č', 'ď', 'é', 'ě', 'í', 'ň', 'ó', 'ř', 'š', 'ť', 'ú', 'ů', 'ý', 'ž');
    $replace = array('-', 'e', 'e', 'e', 'e', 'a', 'a', 'a', 'a', 'a', 'a', 'o', 'o', 'o', 'o', 'o', 'i', 'i', 'i', 'u', 'u', 'u', 'u', 'y', 'y', 'o', 'ae', 'c', 'n', 'ss', 't', 's', 'r', 'z', 'a', 'c', 'd', 'e', 'e', 'i', 'n', 'o', 'r', 's', 't', 'u', 'u', 'y', 'z');
    $result = str_replace($pattern, $replace, $result);

    $i = 1;
    while (file_exists($_SERVER['DOCUMENT_ROOT'] . "/upload/" . $result . ($i > 1 ? " (" . $i . ")" : "") . "." . $ext)) {
        $i++;
    }

    if ($i > 1) {
        $result .= " (" . $i . ")";
    }

    return $result;
}

if (isset($_POST['product_id']) && $_POST['product_id'] > 0) {
    $sql = "UPDATE table_product 
    SET product_name = :name, product_description = :description, product_price = :price, product_stock = :stock, product_author = :author, product_cartoonist = :cartoonist 
    WHERE product_id=:id";
} else {
    $sql = "INSERT INTO table_product (product_name, product_description, product_price, product_stock, product_author, product_cartoonist)
    VALUES (:name, :description, :price, :stock, :author, :cartoonist)";
}

$stmt = $db->prepare($sql);

if (isset($_POST['product_id']) && $_POST['product_id'] > 0) {
    $stmt->bindParam(':id', $_POST['product_id']);
}

if (isset($_POST['product_name'])) {
    $stmt->bindParam(':name', $_POST['product_name']);
}

if (isset($_POST['product_description'])) {
    $stmt->bindParam(':description', $_POST['product_description']);
}

if (isset($_POST['product_price'])) {
    $stmt->bindParam(':price', $_POST['product_price']);
}

if (isset($_POST['product_stock'])) {
    $stmt->bindParam(':stock', $_POST['product_stock']);
}

if (isset($_POST['product_author'])) {
    $stmt->bindParam(':author', $_POST['product_author']);
}

if (isset($_POST['product_cartoonist'])) {
    $stmt->bindParam(':cartoonist', $_POST['product_cartoonist']);
}

$stmt->execute();

if (isset($_FILES['product_image']) && $_FILES['product_image']['name'] != "") {
    $sql = "SELECT product_image FROM table_product
    WHERE product_id = :product_id";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(":product_id", $_POST['product_id'] > 0 ? $_POST['product_id'] : $db->lastInsertId());
    $stmt->execute();

    if ($row = $stmt->fetch()) {
        if ($row['product_image'] != "" && file_exists($_SERVER['DOCUMENT_ROOT'] . "/upload/" . $row['product_image'])) {
            unlink($_SERVER['DOCUMENT_ROOT'] . "/upload/" . $row['product_image']);
        }
    }

    $extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
    $filename = generateFileName($_POST['product_name'], $extension);
    move_uploaded_file(
        $_FILES['product_image']['tmp_name'],
        $_SERVER['DOCUMENT_ROOT'] . "/upload/" . $filename . "." . $extension
    );

    $sql = "UPDATE table_product SET product_image=:product_image 
    WHERE product_id = :product_id";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(":product_image", $filename . "." . $extension); // Value prend la valeur là où on la déclare Param prend en compte les modifications
    $stmt->bindValue(":product_id", $_POST['product_id'] > 0 ? $_POST['product_id'] : $db->lastInsertId());
    $stmt->execute();

    //Redimensionnement de l'image

    switch (strtolower($extension)) {
        case "gif":
            $imgSource = imagecreatefromgif($_SERVER['DOCUMENT_ROOT'] . "/upload/" . $filename . "." . $extension);
            break;
        case "jpg":
        case "jpeg":
            $imgSource = imagecreatefromjpeg($_SERVER['DOCUMENT_ROOT'] . "/upload/" . $filename . "." . $extension);
            break;
        case "png":
            $imgSource = imagecreatefrompng($_SERVER['DOCUMENT_ROOT'] . "/upload/" . $filename . "." . $extension);
            break;

        default:
            exit();//supprimer le fichier avant le exit
    }
    //création de l'images

    $sizes = getimagesize($_SERVER['DOCUMENT_ROOT'] . "/upload/" . $filename . "." . $extension);
    $imgSourceLargeur = $sizes[0];
    $imgSourceHauteur = $sizes[1];

    $tabTailles = [
        ["prefix" => "xl", "largeur" => 1200, "hauteur" => 900],
        ["prefix" => "lg", "largeur" => 800, "hauteur" => 600],
        ["prefix" => "md", "largeur" => 400, "hauteur" => 400],
        ["prefix" => "sm", "largeur" => 150, "hauteur" => 150],
    ];



    foreach ($tabTailles as $taille) {
        $prefix = $taille["prefix"];
        $imgDestLargeur = $taille["largeur"];
        $imgDestHauteur = $taille["hauteur"];
        $imgSourceZoneX = 0;
        $imgSourceZoneY = 0;
        $imgSourceZoneHauteur = $imgSourceHauteur;
        $imgSourceZoneLargeur = $imgSourceLargeur;

        if ($imgDestHauteur == $imgDestLargeur) {
            //crop
            if ($imgSourceLargeur > $imgSourceHauteur) {
                //format paysage
                $imgSourceZoneX = ($imgSourceLargeur - $imgSourceHauteur) / 2;
                $imgSourceZoneLargeur = $imgSourceHauteur;
            } else {
                //format portrait
                $imgSourceZoneY = ($imgSourceHauteur - $imgSourceLargeur) / 2;
                $imgSourceZoneHauteur = $imgSourceLargeur;
            }

        } else {
            //resize
            if ($imgSourceLargeur > $imgSourceHauteur) {
                // format paysage
                $imgDestHauteur = ($imgSourceHauteur * $imgDestLargeur) / $imgSourceLargeur;
            } else {
                // format portrait
                $imgDestLargeur = ($imgSourceLargeur * $imgDestHauteur) / $imgSourceHauteur;
            }

        }
        $imgDest = imagecreatetruecolor($imgDestLargeur, $imgDestHauteur);//création de la nouvelle image a la taille souhaitée

        imagecopyresampled(
            $imgDest,
            $imgSource,
            0,
            0,
            $imgSourceZoneX,
            $imgSourceZoneY,
            $imgDestLargeur,
            $imgDestHauteur,
            $imgSourceLargeur,
            $imgSourceHauteur
        );

        //enregistrement de l'image
        switch (strtolower($extension)) {
            case "gif":
                imagegif($imgDest, $_SERVER['DOCUMENT_ROOT'] . "/upload/" . $prefix . "_" . $filename . "." . $extension);
                break;
            case "jpg":
            case "jpeg":
                imagejpeg($imgDest, $_SERVER['DOCUMENT_ROOT'] . "/upload/" . $prefix . "_" . $filename . "." . $extension, 97);
                break;
            case "png":
                imagepng($imgDest, $_SERVER['DOCUMENT_ROOT'] . "/upload/" . $prefix . "_" . $filename . "." . $extension, 5);
                break;
        }
    }



    //Suppression de l'image source
    unlink($_SERVER['DOCUMENT_ROOT'] . "/upload/" . $filename . "." . $extension);

}

header("Location:index.php");