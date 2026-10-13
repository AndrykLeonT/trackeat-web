<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION trackeat_protect_corte() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF OLD.estado = 'cerrado' THEN
        RAISE EXCEPTION 'El corte cerrado es inmutable' USING ERRCODE = '23514';
    END IF;
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'No se puede purgar un corte abierto' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER protect_corte BEFORE UPDATE OR DELETE ON cortes
FOR EACH ROW EXECUTE FUNCTION trackeat_protect_corte();

CREATE OR REPLACE FUNCTION trackeat_protect_pago() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE corte_estado text; corte_usuario bigint;
BEGIN
    IF TG_OP <> 'INSERT' THEN
        SELECT estado INTO corte_estado FROM cortes WHERE id = OLD.id_corte FOR UPDATE;
        IF corte_estado = 'cerrado' THEN
            RAISE EXCEPTION 'El pago pertenece a un corte cerrado' USING ERRCODE = '23514';
        END IF;
        IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
        IF ROW(NEW.id_pedido, NEW.id_usuario, NEW.id_corte, NEW.metodo_pago, NEW.monto, NEW.propina)
            IS DISTINCT FROM ROW(OLD.id_pedido, OLD.id_usuario, OLD.id_corte, OLD.metodo_pago, OLD.monto, OLD.propina) THEN
            RAISE EXCEPTION 'Corrija el pago mediante baja y un nuevo cobro' USING ERRCODE = '23514';
        END IF;
        IF (OLD.eliminado AND NOT NEW.eliminado) OR (OLD.reembolso AND NOT NEW.reembolso) THEN
            RAISE EXCEPTION 'No se puede reactivar un pago' USING ERRCODE = '23514';
        END IF;
    END IF;
    SELECT estado, id_usuario INTO corte_estado, corte_usuario FROM cortes WHERE id = NEW.id_corte FOR UPDATE;
    IF corte_estado = 'cerrado' OR corte_usuario <> NEW.id_usuario THEN
        RAISE EXCEPTION 'Se requiere el corte abierto del cajero' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER protect_pago BEFORE INSERT OR UPDATE OR DELETE ON pagos
FOR EACH ROW EXECUTE FUNCTION trackeat_protect_pago();

CREATE OR REPLACE FUNCTION trackeat_immutable_row() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'El registro histórico es inmutable' USING ERRCODE = '23514';
END;
$$;
CREATE TRIGGER immutable_item BEFORE UPDATE OR DELETE ON items_pedido
FOR EACH ROW EXECUTE FUNCTION trackeat_immutable_row();
CREATE TRIGGER immutable_historial BEFORE UPDATE OR DELETE ON historial_estados
FOR EACH ROW EXECUTE FUNCTION trackeat_immutable_row();
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER immutable_historial ON historial_estados;
            DROP TRIGGER immutable_item ON items_pedido;
            DROP TRIGGER protect_pago ON pagos;
            DROP TRIGGER protect_corte ON cortes;
            DROP FUNCTION trackeat_immutable_row();
            DROP FUNCTION trackeat_protect_pago();
            DROP FUNCTION trackeat_protect_corte();');
    }
};
