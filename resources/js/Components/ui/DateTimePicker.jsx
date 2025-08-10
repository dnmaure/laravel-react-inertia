import React, { useState } from 'react';
import { format } from 'date-fns';
import { Calendar as CalendarIcon, Clock } from 'lucide-react';
import { cn } from '@/lib/utils';

const DateTimePicker = ({ 
  value, 
  onChange, 
  placeholder = "Pick a date and time", 
  className = "",
  showTime = true,
  showDate = true,
  disabled = false,
  error = null
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [selectedDate, setSelectedDate] = useState(value ? new Date(value) : null);
  const [selectedTime, setSelectedTime] = useState(
    value ? format(new Date(value), 'HH:mm') : '00:00'
  );

  const handleDateSelect = (date) => {
    setSelectedDate(date);
    if (date && selectedTime) {
      const [hours, minutes] = selectedTime.split(':');
      const combinedDateTime = new Date(date);
      combinedDateTime.setHours(parseInt(hours), parseInt(minutes));
      onChange(combinedDateTime);
    }
  };

  const handleTimeSelect = (time) => {
    setSelectedTime(time);
    if (selectedDate && time) {
      const [hours, minutes] = time.split(':');
      const combinedDateTime = new Date(selectedDate);
      combinedDateTime.setHours(parseInt(hours), parseInt(minutes));
      onChange(combinedDateTime);
    }
  };

  const formatDisplayValue = () => {
    if (!value) return '';
    const date = new Date(value);
    if (showDate && showTime) {
      return format(date, 'MMM dd, yyyy HH:mm');
    } else if (showDate) {
      return format(date, 'MMM dd, yyyy');
    } else if (showTime) {
      return format(date, 'HH:mm');
    }
    return '';
  };

  const generateTimeOptions = () => {
    const options = [];
    for (let hour = 0; hour < 24; hour++) {
      for (let minute = 0; minute < 60; minute += 15) {
        const timeString = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
        options.push(timeString);
      }
    }
    return options;
  };

  const generateDateOptions = () => {
    const dates = [];
    const today = new Date();
    for (let i = 0; i < 365; i++) {
      const date = new Date(today);
      date.setDate(today.getDate() + i);
      dates.push(date);
    }
    return dates;
  };

  return (
    <div className={cn("relative", className)}>
      <div
        className={cn(
          "flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50",
          error && "border-red-500 focus:ring-red-500",
          isOpen && "ring-2 ring-ring ring-offset-2"
        )}
        onClick={() => !disabled && setIsOpen(!isOpen)}
      >
        <div className="flex items-center gap-2">
          {showDate && <CalendarIcon className="h-4 w-4" />}
          {showTime && <Clock className="h-4 w-4" />}
          <span className={cn(!value && "text-muted-foreground")}>
            {formatDisplayValue() || placeholder}
          </span>
        </div>
      </div>

      {error && (
        <p className="mt-1 text-sm text-red-500">{error}</p>
      )}

      {isOpen && !disabled && (
        <div className="absolute top-full left-0 z-50 mt-1 w-80 rounded-md border bg-popover p-4 text-popover-foreground shadow-md">
          <div className="space-y-4">
            {showDate && (
              <div>
                <h3 className="text-sm font-medium mb-2">Select Date</h3>
                <div className="grid grid-cols-7 gap-1 max-h-48 overflow-y-auto">
                  {generateDateOptions().map((date, index) => (
                    <button
                      key={index}
                      className={cn(
                        "h-8 w-full rounded text-xs hover:bg-accent hover:text-accent-foreground",
                        selectedDate && format(selectedDate, 'yyyy-MM-dd') === format(date, 'yyyy-MM-dd') && "bg-primary text-primary-foreground"
                      )}
                      onClick={() => handleDateSelect(date)}
                    >
                      {format(date, 'dd')}
                    </button>
                  ))}
                </div>
              </div>
            )}

            {showTime && (
              <div>
                <h3 className="text-sm font-medium mb-2">Select Time</h3>
                <div className="grid grid-cols-4 gap-1 max-h-32 overflow-y-auto">
                  {generateTimeOptions().map((time) => (
                    <button
                      key={time}
                      className={cn(
                        "h-8 w-full rounded text-xs hover:bg-accent hover:text-accent-foreground",
                        selectedTime === time && "bg-primary text-primary-foreground"
                      )}
                      onClick={() => handleTimeSelect(time)}
                    >
                      {time}
                    </button>
                  ))}
                </div>
              </div>
            )}

            <div className="flex justify-end gap-2 pt-2 border-t">
              <button
                className="px-3 py-1 text-xs rounded border hover:bg-accent"
                onClick={() => setIsOpen(false)}
              >
                Cancel
              </button>
              <button
                className="px-3 py-1 text-xs rounded bg-primary text-primary-foreground hover:bg-primary/90"
                onClick={() => setIsOpen(false)}
              >
                Done
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default DateTimePicker; 