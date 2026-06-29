## Qué cambia
<!-- Resumen en 2-4 líneas de qué hace este PR -->


## Por qué
<!-- Contexto del problema o motivación. Si hay un issue, referencialo: Closes #123 -->


## Cómo
<!-- Decisiones técnicas relevantes. Trade-offs considerados. -->


## Cómo se prueba
<!-- Pasos para probar manualmente + cobertura de tests -->


## Riesgos
<!-- Áreas que podrían romperse. Plan de rollback si aplica. -->


## Checklist
- [ ] Tests añadidos/actualizados
- [ ] Migraciones reversibles (`up` y `down` probados)
- [ ] Documentación actualizada (si aplica)
- [ ] Pint pasa localmente (`vendor/bin/pint --test`)
- [ ] Larastan pasa localmente (`vendor/bin/phpstan analyse`)
- [ ] Tests pasan localmente (`php artisan test`)
- [ ] Sin secrets en el diff
- [ ] Si toca el módulo billing, lista las ADRs afectadas:
