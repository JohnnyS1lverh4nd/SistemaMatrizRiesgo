<?php

/*
 * Importación de matrices desde los Excel institucionales (Fase 9, RF-7).
 *
 * Los valores de los Excel se buscan primero por nombre en los catálogos
 * (sin distinguir mayúsculas, acentos ni el punto final). Los alias cubren
 * las formas abreviadas o descriptivas que no coinciden con ningún nombre.
 * Claves en minúsculas y sin acentos; valores = slug del catálogo.
 */

return [

    'alias_factores' => [
        'pnal' => 'personal',
        'tec' => 'tecnologico',
    ],

    'alias_periodicidades' => [
        'revision cada tres meses' => 'trimestral',
    ],

    // Cuando el nivel del Excel no coincide con la matriz de calor.
    'justificacion_nivel' => 'Nivel asignado en el Excel original (importación).',

    // La columna "Seguimiento/Evidencias" se importa como un seguimiento.
    'prefijo_seguimiento' => 'Importado del Excel: ',

];
