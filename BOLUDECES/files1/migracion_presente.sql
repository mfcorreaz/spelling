USE spelling_bee;

ALTER TABLE inscripciones
  ADD COLUMN presente TINYINT(1) DEFAULT 0 AFTER eliminado;
