<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="<?= base_url('estilos/main_page.css') ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>main_page</title>
</head>
<body>
    <header>
        <?php include 'Header.php'; ?>
    </header>
    <div class="vacio"></div>
    <a href="<?= base_url('crea_producto') ?>"><button>Crea producto</button></a>
    <a href="<?= base_url('lista_producto') ?>"><button>Lista producto</button></a><br>
    <a href="<?= base_url('crea_cliente') ?>"><button>Crea cliente</button></a>
    <a href="<?= base_url('lista_cliente') ?>"><button>Lista cliente</button></a><br>
    <a href="<?= base_url('crea_repartidor') ?>"><button>Crea repartidor</button></a>
    <a href="<?= base_url('lista_repartidor') ?>"><button>Lista repartidor</button></a><br>
    <a href="<?= base_url('crea_direccion') ?>"><button>Crea direccion</button></a>
    <a href="<?= base_url('lista_direccion') ?>"><button>Lista direccion</button></a><br>
    <a href="<?= base_url('crea_entrada') ?>"><button>Crea entrada</button></a>
    <a href="<?= base_url('lista_entrada') ?>"><button>Lista entrada</button></a><br>
</body>
</html>