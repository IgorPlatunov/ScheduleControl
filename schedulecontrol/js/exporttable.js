class ExportTable
{
	rows = [];
	columns = 0;

	borderTop = null;
	borderBottom = null;
	borderLeft = null;
	borderRight = null;

	style = {};

	spandata = null;
	subdata = null;
	contentdata = null;
	parentcell = null;

	SetBorder(style, top, right, bottom, left)
	{
		if (right == null) right = top;
		if (bottom == null) bottom = top;
		if (left == null) left = right;

		if (top) this.borderTop = top + " " + style;
		if (right) this.borderRight = right + " " + style;
		if (bottom) this.borderBottom = bottom + " " + style;
		if (left) this.borderLeft = left + " " + style;
	}

	AddRow()
	{
		let row = new ExportTableRow(this);
		this.rows.push(row);

		for (let i = 0; i < this.columns; i++)
			row.SetCell("", i);

		return row;
	}

	GetRows() { return this.rows; }
	GetColumns() { return this.columns; }

	MakeTable()
	{
		this.CalcContentData();

		let tab = document.createElement("table");

		for (let [r, row] of this.contentdata.rows.entries())
		{
			let tr = document.createElement("tr");
			tab.appendChild(tr);

			for (let [c, cell] of row.entries())
			{
				let td = document.createElement("td");
				td.innerText = cell.GetContent();
				tr.appendChild(td);
			}
		}

		return tab;
	}

	CalcSpanData()
	{
		if (this.spandata) return;

		let rows = [];
		let cols = [];

		for (let [r, row] of this.rows.entries())
			for (let [c, cell] of row.GetCells().entries())
			{
				if (rows[r] && rows[r][c]) continue;

				for (let rs = 0; rs < cell.GetRowSpan(); rs++)
					for (let cs = 0; cs < cell.GetColSpan(); cs++)
					{
						let cdata = {cell: cell, rowspan: rs, colspan: cs};

						if (!rows[r + rs]) rows[r + rs] = [];
						rows[r + rs][c + cs] = cdata;

						if (!cols[c + cs]) cols[c + cs] = [];
						cols[c + cs][r + rs] = cdata;
					}
			}

		this.spandata = {rows: rows, cols: cols};
	}

	CalcSubData()
	{
		if (this.subdata) return;

		this.CalcSpanData();

		let rows = [];
		let rowc = 0;

		for (let [r, row] of this.spandata.rows.entries())
		{
			let sub = 1;

			for (let [c, cell] of row.entries())
			{
				let csub = cell.GetSubRows(cell.rowspan);
				if (csub > sub) sub = csub;
			}

			rows.push(sub);
			rowc += sub;
		}

		let cols = [];
		let colc = 0;

		for (let [c, col] of this.spandata.cols.entries())
		{
			let sub = 1;

			for (let [r, cell] of col.entries())
			{
				let csub = cell.GetSubColumns(cell.colspan);
				if (csub > sub) sub = csub;
			}

			cols.push(sub);
			colc += sub;
		}

		this.subdata = {rows: [rows, rowc], cols: [cols, colc]};
	}

	CalcContentData()
	{
		if (this.contentdata) return;

		this.CalcSubData();

		let rows = [];
		let cols = [];

		for (let [r, row] of this.spandata.rows.entries())
			for (let sr = 0; sr < this.subdata.rows[r]; sr++)
			{
				let row = [];

				for (let [c, cdata] of this.subdata.cols.entries())
					for (let sc = 0; sc < this.subdata.cols[c]; sc++)
					{
						let cell = this.rows[r].GetCells()[c];
						let subrow = Math.floor(sr / this.subdata.rows[r] * cell.GetRowSpan());
						let subcol = Math.floor(sc / this.subdata.cols[c] * cell.GetColSpan());
						let subcell = cell.GetSubCell(subrow, subcol);

						row.push(subcell);
					}

				rows.push(row);
			}

		for (let [r, row] of rows.entries())
			for (let [c, cell] of row.entries())
			{
				if (!cols[c]) cols[c] = [];
				cols[c][r] = cell;
			}

		this.contentdata = {rows: rows, cols: cols};
	}

	GetSubRows()
	{
		this.CalcSubData();

		return this.subdata.rows;
	}

	GetSubColumns()
	{
		this.CalcSubData();

		return this.subdata.cols;
	}

	GetSubCell(subrow, subcol)
	{
		this.CalcContentData();

		return this.contentdata.rows[subrow][subcol];
	}

	SetParentCell(cell) { this.parentcell = cell; }
	GetParentCell() { return this.parentcell; }
}

class ExportTableRow
{
	table = null;
	cells = [];

	constructor(table) { this.table = table; }

	borderTop = null;
	borderBottom = null;
	borderLeft = null;
	borderRight = null;

	style = {};

	SetBorder(style, top, right, bottom, left)
	{
		if (right == null) right = top;
		if (bottom == null) bottom = top;
		if (left == null) left = right;

		if (top) this.borderTop = top + " " + style;
		if (right) this.borderRight = right + " " + style;
		if (bottom) this.borderBottom = bottom + " " + style;
		if (left) this.borderLeft = left + " " + style;
	}

	SetCell(content, column)
	{
		if (this.table.GetColumns() <= column)
		{
			this.table.columns = column + 1;

			for (let row of this.table.GetRows())
				for (let i = row.GetCells().length; i <= column; i++)
					row.SetCell("", i);
		}

		let cell = new ExportTableRowCell(this, content);
		this.cells[column] = cell;

		return cell;
	}

	GetCells() { return this.cells; }
	GetTable() { return this.table; }
}

class ExportTableRowCell
{
	row = null;

	borderTop = null;
	borderBottom = null;
	borderLeft = null;
	borderRight = null;

	style = {};
	content = "";
	rowSpan = 1;
	colSpan = 1;

	constructor(row, content)
	{
		this.row = row;
		this.content = content;

		if (this.IsTable()) content.SetParentCell(this);
	}

	SetBorder(style, top, right, bottom, left)
	{
		if (right == null) right = top;
		if (bottom == null) bottom = top;
		if (left == null) left = right;

		if (top) this.borderTop = top + " " + style;
		if (right) this.borderRight = right + " " + style;
		if (bottom) this.borderBottom = bottom + " " + style;
		if (left) this.borderLeft = left + " " + style;
	}

	GetContent() { return this.content; }
	GetRow() { return this.row; }

	SetRowSpan(span) { this.rowSpan = span; }
	GetRowSpan() { return this.rowSpan; }

	SetColSpan(span) { this.colSpan = span; }
	GetColSpan() { return this.colSpan; }

	IsTable() { return typeof this.content == "object" && this.content instanceof ExportTable; }

	GetSubRow(spanindex)
	{
		if (this.IsTable())
			return this.content.GetSubRows()[1] / this.rowSpan * spanindex;

		return 0;
	}

	GetSubColumn(spanindex)
	{
		if (this.IsTable())
			return this.content.GetSubColumns()[1] / this.colSpan * spanindex;

		return 0;
	}

	GetSubRows(spanindex) {
		if (this.IsTable())
			return Math.ceil(this.GetSubRow(spanindex + 1)) - Math.floor(this.GetSubRow(spanindex));

		return 1;
	}

	GetSubColumns(spanindex) {
		if (this.IsTable())
			return Math.ceil(this.GetSubColumn(spanindex + 1)) - Math.floor(this.GetSubColumn(spanindex));

		return 1;
	}

	GetSubCell(subrow, subcol)
	{
		if (this.IsTable())
			return this.content.GetSubCell(subrow, subcol);

		return this;
	}
}