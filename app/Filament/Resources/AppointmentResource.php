<?php
namespace App\Filament\Resources;

use App\Filament\Resources\AppointmentResource\Pages;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Department;
use App\Models\InsuranceProvider;
use App\Models\ServiceAvailability;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;
    protected static ?string $navigationIcon  = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'Appointments';
    protected static ?string $navigationGroup = 'Service Delivery';
    protected static ?int    $navigationSort  = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasAnyRole(['service_provider', 'customer_care', 'admin', 'super_admin']);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['client', 'service', 'provider', 'department']);

        // Department-scoped for service providers
        if (auth()->user()->hasRole('service_provider') && auth()->user()->department_id) {
            $query->where('department_id', auth()->user()->department_id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Client & Service')
                ->schema([
                    Forms\Components\Select::make('client_id')
                        ->label('Client')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) =>
                            Client::withoutGlobalScope('branch')
                                ->where(fn ($q) => $q
                                    ->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name',  'like', "%{$search}%")
                                    ->orWhere('uci',        'like', "%{$search}%")
                                )
                                ->limit(20)
                                ->get()
                                ->mapWithKeys(fn ($c) => [$c->id => "{$c->uci} — {$c->full_name}"])
                                ->toArray()
                        )
                        ->getOptionLabelUsing(fn ($value) =>
                            Client::withoutGlobalScope('branch')->find($value)?->full_name
                        )
                        ->required(),

                    Forms\Components\Select::make('department_id')
                        ->label('Department')
                        ->options(Department::pluck('name', 'id'))
                        ->required()
                        ->live()
                        ->default(fn () => auth()->user()->department_id),

                    Forms\Components\Select::make('service_id')
                        ->label('Service')
                        ->options(fn (\Filament\Forms\Get $get) =>
                            $get('department_id')
                                ? \App\Models\Service::where('department_id', $get('department_id'))->active()->pluck('name', 'id')
                                : []
                        )
                        ->required()
                        ->searchable(),

                    Forms\Components\Select::make('provider_id')
                        ->label('Provider')
                        ->options(fn (\Filament\Forms\Get $get) =>
                            $get('department_id')
                                ? User::where('department_id', $get('department_id'))
                                      ->whereHas('roles', fn ($q) => $q->where('name', 'service_provider'))
                                      ->pluck('name', 'id')
                                : []
                        )
                        ->searchable(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Schedule')
                ->schema([
                    Forms\Components\DatePicker::make('appointment_date')
                        ->required()
                        ->minDate(today())
                        ->live()
                        ->afterStateUpdated(function ($state, \Filament\Forms\Get $get, \Filament\Forms\Set $set) {
                            if (!$state || !$get('department_id')) return;
                            $unavailable = !ServiceAvailability::isDepartmentAvailable(
                                $get('department_id'),
                                \Carbon\Carbon::parse($state)
                            );
                            if ($unavailable) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Department unavailable on this date')
                                    ->send();
                            }
                        }),

                    Forms\Components\TimePicker::make('appointment_time')
                        ->required()
                        ->seconds(false),

                    Forms\Components\TextInput::make('duration')
                        ->label('Duration (minutes)')
                        ->numeric()
                        ->default(60),

                    Forms\Components\Select::make('appointment_type')
                        ->options([
                            'new'       => 'New',
                            'follow_up' => 'Follow-Up',
                            'review'    => 'Review',
                            'emergency' => 'Emergency',
                        ])
                        ->required(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Payment & Notes')
                ->schema([
                    Forms\Components\Select::make('insurance_provider_id')
                        ->label('Insurance Provider')
                        ->options(InsuranceProvider::active()->ordered()->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),

                    Forms\Components\Textarea::make('notes')
                        ->rows(3)
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('send_sms')
                        ->label('Send SMS reminder to client')
                        ->default(true)
                        ->helperText('Logged as mock — no real SMS sent until gateway is configured.'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('appointment_date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('appointment_time')
                    ->time('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.uci')
                    ->label('UCI')
                    ->badge()
                    ->color('info')
                    ->searchable(),

                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Client')
                    ->searchable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('service.name')
                    ->label('Service'),

                Tables\Columns\TextColumn::make('provider.name')
                    ->label('Provider')
                    ->default('—'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'scheduled'  => 'gray',
                        'confirmed'  => 'primary',
                        'checked_in', 'completed' => 'success',
                        'cancelled', 'no_show'    => 'danger',
                        default      => 'gray',
                    }),

                Tables\Columns\IconColumn::make('reminder_sent')
                    ->boolean()
                    ->label('SMS'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'scheduled'  => 'Scheduled',
                        'confirmed'  => 'Confirmed',
                        'checked_in' => 'Checked In',
                        'cancelled'  => 'Cancelled',
                        'no_show'    => 'No Show',
                    ]),

                Tables\Filters\Filter::make('today')
                    ->query(fn ($q) => $q->whereDate('appointment_date', today()))
                    ->label('Today'),

                Tables\Filters\Filter::make('upcoming')
                    ->query(fn ($q) => $q->where('appointment_date', '>=', today()))
                    ->label('Upcoming'),
            ])
            ->actions([
                Tables\Actions\Action::make('confirm')
                    ->label('Confirm')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Appointment $r) => $r->status === 'scheduled')
                    ->action(fn (Appointment $r) => $r->update(['status' => 'confirmed'])),

                Tables\Actions\Action::make('no_show')
                    ->label('No Show')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Appointment $r) => in_array($r->status, ['scheduled', 'confirmed']))
                    ->requiresConfirmation()
                    ->action(fn (Appointment $r) => $r->markNoShow()),

                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('appointment_date', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAppointments::route('/'),
            'create' => Pages\CreateAppointment::route('/create'),
            'edit'   => Pages\EditAppointment::route('/{record}/edit'),
        ];
    }
}
