-- Generado por Oracle SQL Developer Data Modeler 24.3.1.351.0831
--   en:        2026-05-23 19:18:51 PET
--   sitio:      Oracle Database 11g
--   tipo:      Oracle Database 11g



-- predefined type, no DDL - MDSYS.SDO_GEOMETRY

-- predefined type, no DDL - XMLTYPE

CREATE TABLE atencion 
    ( 
     id_atencion        INTEGER  NOT NULL , 
     observacio         VARCHAR2 (50) , 
     total              NUMBER (10,2)  NOT NULL , 
     cliente_id_cliente INTEGER  NOT NULL 
    ) 
;

ALTER TABLE atencion 
    ADD CONSTRAINT atencion_PK PRIMARY KEY ( id_atencion ) ;

CREATE TABLE atencion_producto 
    ( 
     producto_id_producto INTEGER  NOT NULL , 
     atencion_id_atencion INTEGER  NOT NULL , 
     sub_total            NUMBER (10,2)  NOT NULL , 
     cantidad             NUMBER (10)  NOT NULL 
    ) 
;

ALTER TABLE atencion_producto 
    ADD CONSTRAINT atencion_producto_PK PRIMARY KEY ( producto_id_producto, atencion_id_atencion ) ;

CREATE TABLE atencion_servicio 
    ( 
     subtotal             NUMBER (10,2)  NOT NULL , 
     servicio_id_servicio INTEGER  NOT NULL , 
     atencion_id_atencion INTEGER  NOT NULL 
    ) 
;

ALTER TABLE atencion_servicio 
    ADD CONSTRAINT atencion_servicio_PK PRIMARY KEY ( servicio_id_servicio, atencion_id_atencion ) ;

CREATE TABLE categoria 
    ( 
     id_categoria INTEGER  NOT NULL , 
     nombre       VARCHAR2 (50 BYTE)  NOT NULL , 
     descripcion  VARCHAR2 (50 BYTE) , 
     cantidad     NUMBER (10)  NOT NULL , 
     estado       SMALLINT  NOT NULL 
    ) 
;

ALTER TABLE categoria 
    ADD CONSTRAINT categoria_PK PRIMARY KEY ( id_categoria ) ;

CREATE TABLE cliente 
    ( 
     id_cliente       INTEGER  NOT NULL , 
     dni              CHAR (8) , 
     nombre           VARCHAR2 (50)  NOT NULL , 
     apellido_paterno VARCHAR2 (25 BYTE) , 
     apellido_materno VARCHAR2 (25 BYTE) 
    ) 
;

ALTER TABLE cliente 
    ADD CONSTRAINT cliente_PK PRIMARY KEY ( id_cliente ) ;

CREATE TABLE detalle_ingreso 
    ( 
     ingreso_id_ingreso   INTEGER  NOT NULL , 
     producto_id_producto INTEGER  NOT NULL , 
     cantidad             NUMBER (10)  NOT NULL 
    ) 
;

ALTER TABLE detalle_ingreso 
    ADD CONSTRAINT detalle_ingreso_PK PRIMARY KEY ( ingreso_id_ingreso, producto_id_producto ) ;

CREATE TABLE detalle_venta 
    ( 
     sub_total            NUMBER (10,2)  NOT NULL , 
     cantidad             NUMBER (10)  NOT NULL , 
     venta_id_venta       INTEGER  NOT NULL , 
     producto_id_producto INTEGER  NOT NULL 
    ) 
;

ALTER TABLE detalle_venta 
    ADD CONSTRAINT detalle_venta_PK PRIMARY KEY ( venta_id_venta, producto_id_producto ) ;

CREATE TABLE ingreso 
    ( 
     id_ingreso             INTEGER  NOT NULL , 
     correlativo            VARCHAR2 (20)  NOT NULL , 
     proveedor_id_proveedor INTEGER  NOT NULL 
    ) 
;

ALTER TABLE ingreso 
    ADD CONSTRAINT ingreso_PK PRIMARY KEY ( id_ingreso ) ;

CREATE TABLE marca 
    ( 
     id_marca    INTEGER  NOT NULL , 
     nombre      VARCHAR2 (50 BYTE)  NOT NULL , 
     descripcion VARCHAR2 (50 BYTE) , 
     cantidad    NUMBER (10)  NOT NULL , 
     estado      SMALLINT  NOT NULL 
    ) 
;

ALTER TABLE marca 
    ADD CONSTRAINT marca_PK PRIMARY KEY ( id_marca ) ;

CREATE TABLE producto 
    ( 
     id_producto            INTEGER  NOT NULL , 
     nombre                 VARCHAR2 (50 BYTE)  NOT NULL , 
     descripcion            VARCHAR2 (50 BYTE) , 
     precio_compra          NUMBER (10,2) , 
     precio_venta           NUMBER (10,2)  NOT NULL , 
     estado                 SMALLINT  NOT NULL , 
     inventario             NUMBER (10)  NOT NULL , 
     stock                  NUMBER (10)  NOT NULL , 
     categoria_id_categoria INTEGER  NOT NULL , 
     marca_id_marca         INTEGER  NOT NULL 
    ) 
;

ALTER TABLE producto 
    ADD CONSTRAINT producto_PK PRIMARY KEY ( id_producto ) ;

CREATE TABLE proveedor 
    ( 
     id_proveedor INTEGER  NOT NULL , 
     ruc          CHAR (11 BYTE)  NOT NULL , 
     razon_social VARCHAR2 (150 BYTE)  NOT NULL , 
     condicion    VARCHAR2 (15 BYTE)  NOT NULL , 
     estado       SMALLINT  NOT NULL 
    ) 
;

ALTER TABLE proveedor 
    ADD CONSTRAINT proveedor_PK PRIMARY KEY ( id_proveedor ) ;

CREATE TABLE servicio 
    ( 
     id_servicio INTEGER  NOT NULL , 
     nombre      VARCHAR2 (25 BYTE)  NOT NULL , 
     descripcion VARCHAR2 (100 BYTE) , 
     precio      NUMBER (10,2)  NOT NULL 
    ) 
;

ALTER TABLE servicio 
    ADD CONSTRAINT servicio_PK PRIMARY KEY ( id_servicio ) ;

CREATE TABLE venta 
    ( 
     id_venta           INTEGER  NOT NULL , 
     correlativo        VARCHAR2 (15 BYTE)  NOT NULL , 
     total              NUMBER (10,2)  NOT NULL , 
     cliente_id_cliente INTEGER  NOT NULL 
    ) 
;

ALTER TABLE venta 
    ADD CONSTRAINT venta_PK PRIMARY KEY ( id_venta ) ;

ALTER TABLE atencion 
    ADD CONSTRAINT atencion_cliente_FK FOREIGN KEY 
    ( 
     cliente_id_cliente
    ) 
    REFERENCES cliente 
    ( 
     id_cliente
    ) 
;

ALTER TABLE atencion_producto 
    ADD CONSTRAINT atencion_producto_atencion_FK FOREIGN KEY 
    ( 
     atencion_id_atencion
    ) 
    REFERENCES atencion 
    ( 
     id_atencion
    ) 
;

ALTER TABLE atencion_producto 
    ADD CONSTRAINT atencion_producto_FK FOREIGN KEY 
    ( 
     producto_id_producto
    ) 
    REFERENCES producto 
    ( 
     id_producto
    ) 
;

ALTER TABLE atencion_servicio 
    ADD CONSTRAINT atencion_servicio_atencion_FK FOREIGN KEY 
    ( 
     atencion_id_atencion
    ) 
    REFERENCES atencion 
    ( 
     id_atencion
    ) 
;

ALTER TABLE atencion_servicio 
    ADD CONSTRAINT atencion_servicio_servicio_FK FOREIGN KEY 
    ( 
     servicio_id_servicio
    ) 
    REFERENCES servicio 
    ( 
     id_servicio
    ) 
;

ALTER TABLE detalle_ingreso 
    ADD CONSTRAINT detalle_ingreso_ingreso_FK FOREIGN KEY 
    ( 
     ingreso_id_ingreso
    ) 
    REFERENCES ingreso 
    ( 
     id_ingreso
    ) 
;

ALTER TABLE detalle_ingreso 
    ADD CONSTRAINT detalle_ingreso_producto_FK FOREIGN KEY 
    ( 
     producto_id_producto
    ) 
    REFERENCES producto 
    ( 
     id_producto
    ) 
;

ALTER TABLE detalle_venta 
    ADD CONSTRAINT detalle_venta_producto_FK FOREIGN KEY 
    ( 
     producto_id_producto
    ) 
    REFERENCES producto 
    ( 
     id_producto
    ) 
;

ALTER TABLE detalle_venta 
    ADD CONSTRAINT detalle_venta_venta_FK FOREIGN KEY 
    ( 
     venta_id_venta
    ) 
    REFERENCES venta 
    ( 
     id_venta
    ) 
;

ALTER TABLE ingreso 
    ADD CONSTRAINT ingreso_proveedor_FK FOREIGN KEY 
    ( 
     proveedor_id_proveedor
    ) 
    REFERENCES proveedor 
    ( 
     id_proveedor
    ) 
;

ALTER TABLE producto 
    ADD CONSTRAINT producto_categoria_FK FOREIGN KEY 
    ( 
     categoria_id_categoria
    ) 
    REFERENCES categoria 
    ( 
     id_categoria
    ) 
;

ALTER TABLE producto 
    ADD CONSTRAINT producto_marca_FK FOREIGN KEY 
    ( 
     marca_id_marca
    ) 
    REFERENCES marca 
    ( 
     id_marca
    ) 
;

ALTER TABLE venta 
    ADD CONSTRAINT venta_cliente_FK FOREIGN KEY 
    ( 
     cliente_id_cliente
    ) 
    REFERENCES cliente 
    ( 
     id_cliente
    ) 
;
