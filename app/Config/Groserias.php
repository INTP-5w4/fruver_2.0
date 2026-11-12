<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Palabras que no se permiten en ningún texto que se escribe en el sistema
 * (nombres, usuarios, direcciones, notas, sugerencias, mensajes a clientes…).
 * Las usa App\Libraries\FiltroGroserias y la regla de validación "sin_groserias".
 *
 * Cómo escribir la lista (se puede editar sin tocar código):
 *  - En minúsculas y sin acentos; la ñ sí se escribe como ñ.
 *  - Una palabra se compara completa: "culo" no bloquea "Culiacán".
 *  - Con * al final se bloquea todo lo que empiece así: "pendej*" bloquea pendejo, pendejada…
 *    Úsalo solo si ninguna palabra normal empieza igual.
 *  - Con espacios es una frase: "hijo de puta".
 *
 * El filtro ya reconoce los disfraces comunes: mayúsculas, acentos, números por letras
 * (put0, p3ndejo), letras repetidas (puuuto), letras separadas (p u t o, p.u.t.o) y v/k por u/c.
 *
 */
class Groserias extends BaseConfig
{
    /** @var list<string> */
    public array $bloqueadas = [
        // Groserías comunes
        'puto', 'puta', 'putos', 'putas', 'putita', 'putito', 'putitos', 'putitas', 'puton', 'putona',
        'putazo', 'putazos', 'putiza', 'putero', 'puteria', 'putisimo', 'putisima', 'putear',
        'pendej*', 'ching*', 'cabron*', 'mierd*', 'culer*', 'maric*', 'malparid*', 'hijueput*',
        'verga', 'vergas', 'vergazo', 'vergazos', 'vergota', 'vergotas', 'verguiza', 'vergudo', 'vergon',
        'culo', 'culos', 'culito', 'culiao', 'culiado',
        'joto', 'jotos', 'jotito', 'jotitos',
        'mamada', 'mamadas', 'mames', 'mamahuev*', 'chupaverga*', 'chupapija*',
        'pinche', 'pinches', 'carajo', 'carajos', 'coño', 'cojones', 'cojudo', 'cojuda',
        'huevon', 'huevona', 'huevones', 'guevon', 'guevona', 'webon', 'webona',
        'ojete', 'ojetes', 'chichis', 'chichotas', 'tetas', 'tetotas',
        'zorra', 'zorras', 'perra', 'perras', 'prostituta', 'prostitutas',
        'joder', 'jodete', 'jodanse', 'puñeta', 'puñetas', 'puñetero', 'puñetera',
        'cagada', 'cagadas', 'cagar', 'cagon', 'cagona',
        'madrazo', 'madrazos', 'madrear', 'valemadre*', 'valiendo madre',
        // Insultos
        'estupido', 'estupida', 'estupidos', 'estupidas', 'idiota', 'idiotas', 'imbecil', 'imbeciles',
        // Abreviaturas
        // ("alv" y "ctm" no se incluyen: son "Alv. Obregón" y la colonia CTM en muchas direcciones.)
        'ptm', 'hdp', 'hdtpm', 'chtm', 'pndj', 'pndjo', 'vrg', 'vrga', 'vrgs', 'nmms',
        // Frases
        'hijo de puta', 'hija de puta', 'hijos de puta', 'chinga tu madre', 'a la verga', 'no mames',
        'vale madre', 'me vale madre', 'tu puta madre',
    ];

    /**
     * Palabras normales que una entrada con * podría bloquear por error.
     *
     * @var list<string>
     */
    public array $permitidas = [
        'chingolo', 'chingolos',
    ];

    /**
     * Partes de palabras normales que contienen una grosería. Solo se usan con los nombres de
     * usuario, que se revisan "por dentro" porque ahí las palabras van pegadas ("elputo99").
     *
     * @var list<string>
     */
    public array $permitidasDentro = [
        'comput', 'disput', 'diput', 'reput', 'imput', 'amput',         // puta / puto
        'articul', 'calcul', 'vehicul', 'ridicul', 'circul', 'muscul', 'cubicul', 'curricul', // culo
        'chingolo',
    ];
}
