// Power BI Desktop > Obtener datos > Consulta en blanco > Editor avanzado.
// Sustituya la ruta por una exportación privada del tablero. Nombre de consulta: Disfrute.
let
    RutaCSV = "C:\DatosPrivados\disfrute-power-bi.csv",
    Origen = Csv.Document(File.Contents(RutaCSV), [Delimiter=";", Encoding=65001, QuoteStyle=QuoteStyle.Csv]),
    Encabezados = Table.PromoteHeaders(Origen, [PromoteAllScalars=true]),
    Tipos = Table.TransformColumnTypes(Encabezados, {{"solicitud_id", Int64.Type}, {"vigencia", Int64.Type}, {"dependencia_id", Int64.Type}, {"dependencia", type text}, {"familia", type text}, {"tipo", type text}, {"segundos_disfrutados", Int64.Type}})
in
    Tipos
