<?php

/*
 * Laravel es solo la API: la interfaz vive en el frontend Nuxt.
 * Las rutas web que existen las registran Fortify (login, logout,
 * recuperación de contraseña) y Sanctum (/sanctum/csrf-cookie), porque
 * necesitan la sesión y la protección CSRF del grupo 'web'.
 */
