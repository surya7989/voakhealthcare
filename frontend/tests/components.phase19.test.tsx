import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import React from 'react';
import {
  Button,
  IconButton,
  Input,
  Badge,
  StatusBadge,
  FormField,
  FieldLabel,
  FieldDescription,
  FieldError,
  ValidationSummary,
  Table,
  TableHeader,
  TableBody,
  TableRow,
  TableHead,
  TableCell,
  RadioGroup,
  Radio,
  Switch,
  SearchInput,
  FilterBar,
  FilterChip,
  ClearFiltersButton,
  BentoCard,
  ConfirmationDialog,
  Drawer,
  DropdownMenu,
  DropdownMenuItem,
  Tooltip,
  Popover,
  Tabs,
  TabList,
  Tab,
  TabPanels,
  TabPanel,
  Accordion,
  AccordionItem,
  AccordionTrigger,
  AccordionContent,
  FileUploadDropzone,
  DateInput,
  DateTimeDisplay,
  ProgressBar,
  DataTable,
  DataTableColumn,
} from '../src/shared/components';
import { Plus } from 'lucide-react';

describe('Phase 19 Complete UI Component System', () => {
  describe('Button & IconButton Extensions', () => {
    it('renders pill variants and IconButton with accessible label', () => {
      render(
        <div>
          <Button variant="pill-pink">Pill Pink</Button>
          <Button variant="pill-yellow">Pill Yellow</Button>
          <Button variant="pill-mint">Pill Mint</Button>
          <IconButton icon={<Plus data-testid="plus-icon" />} aria-label="Add record" />
        </div>
      );

      expect(screen.getByRole('button', { name: /pill pink/i })).toHaveClass('rounded-full');
      expect(screen.getByRole('button', { name: /pill yellow/i })).toHaveClass('rounded-full');
      expect(screen.getByRole('button', { name: /pill mint/i })).toHaveClass('rounded-full');

      const iconBtn = screen.getByRole('button', { name: /add record/i });
      expect(iconBtn).toBeInTheDocument();
      expect(screen.getByTestId('plus-icon')).toBeInTheDocument();
    });
  });

  describe('Input Extensions', () => {
    it('renders Input with left and right icon slots and sizes', () => {
      render(
        <Input
          placeholder="Search items"
          leftIcon={<span data-testid="left-icon">L</span>}
          rightIcon={<span data-testid="right-icon">R</span>}
          size="lg"
        />
      );

      expect(screen.getByPlaceholderText(/search items/i)).toHaveClass('pl-10');
      expect(screen.getByTestId('left-icon')).toBeInTheDocument();
      expect(screen.getByTestId('right-icon')).toBeInTheDocument();
    });
  });

  describe('Badge & StatusBadge', () => {
    it('renders Badge with dot indicator and new brand tokens', () => {
      const { container } = render(
        <div>
          <Badge variant="mint" dot>Active</Badge>
          <Badge variant="sky">Info</Badge>
          <Badge variant="navy">System</Badge>
        </div>
      );

      expect(screen.getByText('Active')).toBeInTheDocument();
      expect(container.querySelector('.rounded-full.bg-voak-mint')).toBeInTheDocument();
    });

    it('renders StatusBadge mapping semantic statuses correctly', () => {
      render(
        <div>
          <StatusBadge status="published" />
          <StatusBadge status="draft" />
          <StatusBadge status="failed" />
          <StatusBadge status="custom_state" variantMap={{ custom_state: 'pink' }} />
        </div>
      );

      expect(screen.getByText('Published')).toBeInTheDocument();
      expect(screen.getByText('Draft')).toBeInTheDocument();
      expect(screen.getByText('Failed')).toBeInTheDocument();
      expect(screen.getByText('Custom_state')).toBeInTheDocument();
    });
  });

  describe('FormField Modular Subcomponents', () => {
    it('renders FieldLabel, FieldDescription, FieldError, and ValidationSummary', () => {
      render(
        <div>
          <FormField id="test-email" label="Email Address" required error="Invalid email address" helperText="Enter work email">
            <Input id="test-email" />
          </FormField>
          <FieldLabel htmlFor="custom-input" required>Custom Label</FieldLabel>
          <FieldDescription id="custom-desc">Some helper text</FieldDescription>
          <FieldError error="Standalone error" />
          <ValidationSummary errors={['Error 1', 'Error 2']} />
        </div>
      );

      expect(screen.getByText('Email Address')).toBeInTheDocument();
      expect(screen.getByText('Invalid email address')).toBeInTheDocument();
      expect(screen.getByText('Error 1')).toBeInTheDocument();
      expect(screen.getByText('Error 2')).toBeInTheDocument();
    });
  });

  describe('Table & Sortable TableHead', () => {
    it('handles sorting callbacks and accessibility attributes on TableHead', () => {
      const handleSort = vi.fn();
      render(
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead sortDirection="asc" onSort={handleSort}>Name</TableHead>
              <TableHead>Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow>
              <TableCell>John Doe</TableCell>
              <TableCell>Edit</TableCell>
            </TableRow>
          </TableBody>
        </Table>
      );

      const nameTh = screen.getByRole('columnheader', { name: /name/i });
      expect(nameTh).toHaveAttribute('aria-sort', 'ascending');
      fireEvent.click(nameTh);
      expect(handleSort).toHaveBeenCalledTimes(1);

      fireEvent.keyDown(nameTh, { key: 'Enter' });
      expect(handleSort).toHaveBeenCalledTimes(2);
    });
  });

  describe('RadioGroup & Radio', () => {
    it('manages selection and keyboard arrow navigation', () => {
      const handleChange = vi.fn();
      render(
        <RadioGroup value="option-b" onChange={handleChange} label="Select Plan">
          <Radio value="option-a" label="Plan A" />
          <Radio value="option-b" label="Plan B" />
          <Radio value="option-c" label="Plan C" />
        </RadioGroup>
      );

      const group = screen.getByRole('radiogroup', { name: /select plan/i });
      expect(group).toBeInTheDocument();

      const radioA = screen.getByRole('radio', { name: /plan a/i });
      const radioB = screen.getByRole('radio', { name: /plan b/i });
      expect(radioB).toBeChecked();
      expect(radioA).not.toBeChecked();

      fireEvent.click(radioA);
      expect(handleChange).toHaveBeenCalledWith('option-a');

      // Test arrow navigation
      fireEvent.keyDown(group, { key: 'ArrowDown' });
      expect(handleChange).toHaveBeenCalled();
    });
  });

  describe('Switch Component', () => {
    it('toggles on click and keyboard Space/Enter', () => {
      const handleChange = vi.fn();
      render(
        <Switch
          label="Email Notifications"
          defaultChecked={false}
          onChange={handleChange}
        />
      );

      const switchBtn = screen.getByRole('switch', { name: /email notifications/i });
      expect(switchBtn).toHaveAttribute('aria-checked', 'false');

      fireEvent.click(switchBtn);
      expect(switchBtn).toHaveAttribute('aria-checked', 'true');
      expect(handleChange).toHaveBeenCalledWith(true);

      fireEvent.keyDown(switchBtn, { key: ' ' });
      expect(switchBtn).toHaveAttribute('aria-checked', 'false');
      expect(handleChange).toHaveBeenCalledWith(false);
    });
  });

  describe('SearchInput Component', () => {
    it('handles input, search on Enter, and clears on button click or Escape', () => {
      const handleSearch = vi.fn();
      const handleClear = vi.fn();
      render(
        <SearchInput
          defaultValue="initial search"
          onSearch={handleSearch}
          onClear={handleClear}
        />
      );

      const input = screen.getByRole('searchbox');
      expect(input).toHaveValue('initial search');

      const clearBtn = screen.getByRole('button', { name: /clear search input/i });
      fireEvent.click(clearBtn);
      expect(handleClear).toHaveBeenCalledTimes(1);

      fireEvent.change(input, { target: { value: 'query' } });
      fireEvent.keyDown(input, { key: 'Enter' });
      expect(handleSearch).toHaveBeenCalledWith('query');

      fireEvent.keyDown(input, { key: 'Escape' });
      expect(input).toHaveValue('');
    });
  });

  describe('FilterBar, FilterChip & ClearFiltersButton', () => {
    it('renders active chips and triggers removal and clear callbacks', () => {
      const handleRemove = vi.fn();
      const handleClearAll = vi.fn();

      render(
        <FilterBar
          onClearAll={handleClearAll}
          activeFilters={
            <FilterChip label="Role" value="Administrator" onRemove={handleRemove} />
          }
        >
          <div>Filter Controls</div>
        </FilterBar>
      );

      expect(screen.getByText('Filter Controls')).toBeInTheDocument();
      expect(screen.getByText(': Administrator')).toBeInTheDocument();

      const removeBtn = screen.getByRole('button', { name: /remove filter for role/i });
      fireEvent.click(removeBtn);
      expect(handleRemove).toHaveBeenCalledTimes(1);

      const clearAllBtn = screen.getByRole('button', { name: /reset filters/i });
      fireEvent.click(clearAllBtn);
      expect(handleClearAll).toHaveBeenCalledTimes(1);

      render(<ClearFiltersButton onClear={handleClearAll} label="Custom Reset" />);
      fireEvent.click(screen.getByRole('button', { name: /custom reset/i }));
      expect(handleClearAll).toHaveBeenCalledTimes(2);
    });
  });

  describe('BentoCard Component', () => {
    it('renders BentoCard with header, title, subtitle, and tint', () => {
      render(
        <BentoCard
          colSpan={6}
          tint="pink"
          title="Clinical Operations"
          subtitle="Real-time patient flow analytics"
          chip={<Badge variant="pink">Live</Badge>}
        >
          <div data-testid="bento-children">Card Content</div>
        </BentoCard>
      );

      expect(screen.getByText('Clinical Operations')).toBeInTheDocument();
      expect(screen.getByText('Real-time patient flow analytics')).toBeInTheDocument();
      expect(screen.getByTestId('bento-children')).toBeInTheDocument();
    });
  });

  describe('ConfirmationDialog Component', () => {
    it('renders confirmation modal and handles confirm and cancel', () => {
      const handleConfirm = vi.fn();
      const handleClose = vi.fn();

      render(
        <ConfirmationDialog
          isOpen={true}
          title="Delete Article"
          message="Are you sure you want to delete this article? This action cannot be undone."
          confirmText="Yes, Delete"
          cancelText="No, Keep"
          variant="danger"
          onConfirm={handleConfirm}
          onClose={handleClose}
        />
      );

      expect(screen.getByText('Delete Article')).toBeInTheDocument();
      expect(screen.getByText(/are you sure you want to delete/i)).toBeInTheDocument();

      const confirmBtn = screen.getByRole('button', { name: /yes, delete/i });
      fireEvent.click(confirmBtn);
      expect(handleConfirm).toHaveBeenCalledTimes(1);

      const cancelBtn = screen.getByRole('button', { name: /no, keep/i });
      fireEvent.click(cancelBtn);
      expect(handleClose).toHaveBeenCalledTimes(1);
    });
  });

  describe('Drawer Component', () => {
    it('renders drawer when open and closes on ESC or close button', () => {
      const handleClose = vi.fn();
      const { rerender } = render(
        <Drawer
          isOpen={true}
          onClose={handleClose}
          title="Drawer Title"
          description="Drawer description"
        >
          <p>Drawer Body Content</p>
        </Drawer>
      );

      expect(screen.getByRole('dialog', { name: /drawer title/i })).toBeInTheDocument();
      expect(screen.getByText('Drawer Body Content')).toBeInTheDocument();

      const closeBtn = screen.getByRole('button', { name: /close panel/i });
      fireEvent.click(closeBtn);
      expect(handleClose).toHaveBeenCalledTimes(1);

      fireEvent.keyDown(document, { key: 'Escape' });
      expect(handleClose).toHaveBeenCalledTimes(2);

      rerender(
        <Drawer
          isOpen={false}
          onClose={handleClose}
          title="Drawer Title"
        >
          <p>Drawer Body Content</p>
        </Drawer>
      );
      expect(screen.queryByText('Drawer Body Content')).not.toBeInTheDocument();
    });
  });

  describe('DropdownMenu Component', () => {
    it('opens on trigger click, navigates items with keyboard, and fires onSelect', () => {
      const handleSelect = vi.fn();
      render(
        <DropdownMenu trigger={<button type="button">Actions Menu</button>}>
          <DropdownMenuItem onSelect={handleSelect}>Edit Item</DropdownMenuItem>
          <DropdownMenuItem destructive>Delete Item</DropdownMenuItem>
        </DropdownMenu>
      );

      const trigger = screen.getByRole('button', { name: /actions menu/i });
      expect(trigger).toHaveAttribute('aria-expanded', 'false');

      fireEvent.click(trigger);
      expect(trigger).toHaveAttribute('aria-expanded', 'true');

      const menu = screen.getByRole('menu');
      expect(menu).toBeInTheDocument();

      const editItem = screen.getByRole('menuitem', { name: /edit item/i });
      fireEvent.click(editItem);
      expect(handleSelect).toHaveBeenCalledTimes(1);
      expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });
  });

  describe('Tooltip Component', () => {
    it('shows tooltip content on hover/focus and dismisses on Escape', async () => {
      render(
        <Tooltip content="Helper information">
          <button type="button">Hover me</button>
        </Tooltip>
      );

      const trigger = screen.getByRole('button', { name: /hover me/i });
      fireEvent.mouseEnter(trigger);

      await React.act(async () => {
        await new Promise((resolve) => setTimeout(resolve, 200));
      });

      const tooltip = screen.getByRole('tooltip');
      expect(tooltip).toHaveTextContent('Helper information');

      fireEvent.keyDown(trigger, { key: 'Escape' });
      expect(screen.queryByRole('tooltip')).not.toBeInTheDocument();
    });
  });

  describe('Popover Component', () => {
    it('opens on trigger click and renders contextual content', () => {
      render(
        <Popover trigger={<button type="button">Open Popover</button>}>
          <div data-testid="popover-content">Popover Body</div>
        </Popover>
      );

      const trigger = screen.getByRole('button', { name: /open popover/i });
      expect(screen.queryByTestId('popover-content')).not.toBeInTheDocument();

      fireEvent.click(trigger);
      expect(screen.getByTestId('popover-content')).toBeInTheDocument();

      fireEvent.keyDown(document, { key: 'Escape' });
      expect(screen.queryByTestId('popover-content')).not.toBeInTheDocument();
    });
  });

  describe('Tabs Component', () => {
    it('handles tab switching and ARIA tabpanel accessibility', () => {
      const handleChange = vi.fn();
      render(
        <Tabs defaultValue="overview" onChange={handleChange}>
          <TabList>
            <Tab value="overview">Overview</Tab>
            <Tab value="details">Details</Tab>
          </TabList>
          <TabPanels>
            <TabPanel value="overview">Overview Content</TabPanel>
            <TabPanel value="details">Details Content</TabPanel>
          </TabPanels>
        </Tabs>
      );

      const overviewTab = screen.getByRole('tab', { name: /overview/i });
      const detailsTab = screen.getByRole('tab', { name: /details/i });

      expect(overviewTab).toHaveAttribute('aria-selected', 'true');
      expect(screen.getByText('Overview Content')).toBeInTheDocument();
      expect(screen.queryByText('Details Content')).not.toBeInTheDocument();

      fireEvent.click(detailsTab);
      expect(detailsTab).toHaveAttribute('aria-selected', 'true');
      expect(screen.getByText('Details Content')).toBeInTheDocument();
      expect(handleChange).toHaveBeenCalledWith('details');
    });
  });

  describe('Accordion Component', () => {
    it('toggles accordion items and supports aria-expanded', () => {
      render(
        <Accordion type="single" defaultValue="item-1">
          <AccordionItem value="item-1">
            <AccordionTrigger>Section 1</AccordionTrigger>
            <AccordionContent>Content 1</AccordionContent>
          </AccordionItem>
          <AccordionItem value="item-2">
            <AccordionTrigger>Section 2</AccordionTrigger>
            <AccordionContent>Content 2</AccordionContent>
          </AccordionItem>
        </Accordion>
      );

      const trigger1 = screen.getByRole('button', { name: /section 1/i });
      const trigger2 = screen.getByRole('button', { name: /section 2/i });

      expect(trigger1).toHaveAttribute('aria-expanded', 'true');
      expect(screen.getByText('Content 1')).toBeInTheDocument();
      expect(screen.queryByText('Content 2')).not.toBeInTheDocument();

      fireEvent.click(trigger2);
      expect(trigger2).toHaveAttribute('aria-expanded', 'true');
      expect(trigger1).toHaveAttribute('aria-expanded', 'false');
      expect(screen.getByText('Content 2')).toBeInTheDocument();
    });
  });

  describe('FileUploadDropzone Component', () => {
    it('renders dropzone, displays file list, and handles remove', () => {
      const handleRemove = vi.fn();
      const fakeFile = new File(['hello world'], 'clinical-report.pdf', { type: 'application/pdf' });

      render(
        <FileUploadDropzone
          files={[fakeFile]}
          onFileRemove={handleRemove}
          label="Upload Medical Records"
        />
      );

      expect(screen.getByText(/upload medical records/i)).toBeInTheDocument();
      expect(screen.getByText('clinical-report.pdf')).toBeInTheDocument();

      const removeBtn = screen.getByRole('button', { name: /remove file clinical-report.pdf/i });
      fireEvent.click(removeBtn);
      expect(handleRemove).toHaveBeenCalledWith(0);
    });
  });

  describe('DateInput & DateTimeDisplay', () => {
    it('renders native date input and formats DateTimeDisplay', () => {
      render(
        <div>
          <DateInput aria-label="Appointment Date" defaultValue="2026-10-24" />
          <DateTimeDisplay value="2026-10-24T12:00:00Z" format="date" />
        </div>
      );

      const dateInput = screen.getByLabelText('Appointment Date');
      expect(dateInput).toHaveValue('2026-10-24');
      expect(screen.getByText(/Oct 24, 2026/i)).toBeInTheDocument();
    });
  });

  describe('ProgressBar Component', () => {
    it('renders progressbar with proper ARIA attributes and percentage label', () => {
      render(
        <ProgressBar
          value={65}
          max={100}
          label="System Health"
          showLabel={true}
          variant="mint"
        />
      );

      const progressBar = screen.getByRole('progressbar', { name: /system health/i });
      expect(progressBar).toHaveAttribute('aria-valuenow', '65');
      expect(progressBar).toHaveAttribute('aria-valuemin', '0');
      expect(progressBar).toHaveAttribute('aria-valuemax', '100');
      expect(screen.getByText('65%')).toBeInTheDocument();
    });
  });

  describe('DataTable Generic Grid', () => {
    interface UserRow {
      id: string;
      name: string;
      role: string;
      status: string;
    }

    const testColumns: DataTableColumn<UserRow>[] = [
      { key: 'name', header: 'Full Name', sortable: true },
      { key: 'role', header: 'Role' },
      {
        key: 'status',
        header: 'Status',
        render: (row) => <StatusBadge status={row.status} />,
      },
    ];

    const testData: UserRow[] = [
      { id: '1', name: 'Dr. Sarah Connor', role: 'Chief Medical Officer', status: 'active' },
      { id: '2', name: 'James Wilson', role: 'Staff Nurse', status: 'pending' },
    ];

    it('renders columns, rows, sorting, and handles row selection', () => {
      const handleSort = vi.fn();
      const handleSelectionChange = vi.fn();

      render(
        <DataTable
          data={testData}
          columns={testColumns}
          sortKey="name"
          sortDirection="asc"
          onSort={handleSort}
          selectable={true}
          selectedIds={['1']}
          onSelectionChange={handleSelectionChange}
        />
      );

      expect(screen.getByText('Dr. Sarah Connor')).toBeInTheDocument();
      expect(screen.getByText('James Wilson')).toBeInTheDocument();

      // Check sorting header
      const nameHeader = screen.getByRole('columnheader', { name: /full name/i });
      expect(nameHeader).toHaveAttribute('aria-sort', 'ascending');
      fireEvent.click(nameHeader);
      expect(handleSort).toHaveBeenCalledWith('name');

      // Check row selection
      const selectAllCb = screen.getByRole('checkbox', { name: /select all rows/i });
      expect(selectAllCb).toBeInTheDocument();
      fireEvent.click(selectAllCb);
      expect(handleSelectionChange).toHaveBeenCalled();
    });

    it('renders empty and loading states properly', () => {
      const { rerender } = render(
        <DataTable
          data={[]}
          columns={testColumns}
          emptyTitle="No staff found"
          emptyDescription="Try adjusting your search criteria."
        />
      );

      expect(screen.getByText('No staff found')).toBeInTheDocument();

      rerender(
        <DataTable
          data={[]}
          columns={testColumns}
          isLoading={true}
        />
      );

      // Skeletons are rendered during loading
      expect(document.querySelector('.animate-pulse')).toBeInTheDocument();
    });
  });
});
