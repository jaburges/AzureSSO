# The Events Calendar + Outlook Integration Tasks

## 🎯 Project Overview

**Goal**: Integrate The Events Calendar (TEC) WordPress plugin with Microsoft Outlook Calendar via Azure Graph API to enable bidirectional sync while maintaining full TEC compatibility.

**Key Benefits**:
- Keep all TEC functionality (shortcodes, tickets, third-party plugins)
- Real bidirectional sync with Outlook (not just embedding)
- Mobile calendar access via native phone calendar apps
- Simplified management (fixed venue: "School", fixed organizer: "PTSA")
- Professional email notifications through Outlook

## 🏗️ Technical Approach

### Database Strategy
- **Primary**: Use existing TEC database structure (`wp_posts`, `wp_postmeta`, `wp_term_relationships`)
- **Enhancement**: Add Outlook Event ID metadata to TEC events for sync tracking
- **Sync Direction**: Bidirectional with conflict resolution based on last modified timestamps

### Integration Points
- Hook into TEC event lifecycle (create, update, delete)
- Periodic pulls from Outlook to catch external changes
- Map TEC event fields to Microsoft Graph API event format
- Handle recurring events, timezones, and conflict resolution

---

## ✅ Task Checklist

### **Phase 1: Foundation & Setup** 
*Estimated: 1-2 weeks*

#### Core Infrastructure
- [ ] **1.1** Create `Azure_TEC_Integration` main class in `includes/class-tec-integration.php`
- [ ] **1.2** Create `Azure_TEC_Sync_Engine` class for sync logic in `includes/class-tec-sync-engine.php`  
- [ ] **1.3** Create `Azure_TEC_Data_Mapper` class for data transformation in `includes/class-tec-data-mapper.php`
- [ ] **1.4** Add TEC integration to main plugin initialization in `azure-plugin.php`
- [ ] **1.5** Create settings section for TEC integration in admin panel

#### Database & Metadata
- [ ] **1.6** Design metadata schema for sync tracking:
  - `_outlook_event_id` - Store Outlook event ID
  - `_outlook_sync_status` - Track sync status (synced, pending, error)
  - `_outlook_last_sync` - Last sync timestamp
  - `_sync_conflict_resolution` - Track conflict resolution method
- [ ] **1.7** Create database migration function for existing TEC events
- [ ] **1.8** Add sync metadata cleanup functions

#### WordPress Integration
- [ ] **1.9** Register WordPress hooks for TEC events:
  ```php
  add_action('save_post_tribe_events', 'sync_tec_event_to_outlook', 20, 2);
  add_action('before_delete_post', 'delete_outlook_event_from_tec');
  add_action('tribe_events_update_meta', 'handle_tec_meta_update');
  ```
- [ ] **1.10** Create admin notices for TEC plugin dependency
- [ ] **1.11** Add activation check to ensure TEC is active

---

### **Phase 2: Data Mapping & One-Way Sync (TEC → Outlook)**
*Estimated: 1-2 weeks*

#### Data Mapping Engine
- [ ] **2.1** Implement `map_tec_to_outlook()` method:
  - Event title → Outlook subject
  - Event description → Outlook body
  - Start/end dates with timezone handling
  - Fixed location: "School Campus" 
  - Fixed organizer: "PTSA"
- [ ] **2.2** Handle TEC custom fields mapping
- [ ] **2.3** Implement recurring events mapping (TEC → Outlook recurrence patterns)
- [ ] **2.4** Add timezone conversion logic (WordPress timezone → Outlook timezone)

#### TEC to Outlook Sync
- [ ] **2.5** Implement `sync_tec_event_to_outlook()` function:
  - Check if event already exists in Outlook
  - Create new Outlook event via Graph API
  - Update existing Outlook event
  - Store Outlook event ID in TEC metadata
- [ ] **2.6** Handle event deletion sync
- [ ] **2.7** Add comprehensive error handling and logging
- [ ] **2.8** Implement retry logic for failed sync attempts

#### Event Lifecycle Management  
- [ ] **2.9** Handle TEC event status changes (draft, published, private)
- [ ] **2.10** Sync event updates (title, time, description changes)
- [ ] **2.11** Handle bulk operations on TEC events
- [ ] **2.12** Add manual sync trigger for individual events

---

### **Phase 3: Bidirectional Sync (Outlook → TEC)**
*Estimated: 1-2 weeks*

#### Outlook to TEC Sync
- [ ] **3.1** Implement `map_outlook_to_tec()` method:
  - Outlook event → TEC post creation
  - Handle Outlook recurrence → TEC recurring events
  - Map Outlook attendees (if needed)
  - Set proper TEC event categories/tags
- [ ] **3.2** Create `sync_outlook_to_tec()` scheduled function
- [ ] **3.3** Implement `find_tec_event_by_outlook_id()` lookup function
- [ ] **3.4** Handle new events created in Outlook
- [ ] **3.5** Update existing TEC events from Outlook changes

#### Conflict Resolution System
- [ ] **3.6** Implement `resolve_sync_conflict()` method:
  - Compare last modified timestamps
  - Implement conflict resolution strategies:
    - Outlook wins (default)
    - TEC wins  
    - Manual resolution
    - Merge changes (where possible)
- [ ] **3.7** Add conflict notification system
- [ ] **3.8** Create conflict resolution admin interface
- [ ] **3.9** Log all conflict resolutions for audit trail

#### Scheduled Sync Management
- [ ] **3.10** Implement WordPress cron job for regular Outlook pulls
- [ ] **3.11** Add sync frequency settings (hourly, daily, manual)
- [ ] **3.12** Create sync status monitoring
- [ ] **3.13** Handle API rate limiting and throttling

---

### **Phase 4: Advanced Features & Admin Interface**
*Estimated: 1-2 weeks*

#### Admin Dashboard
- [ ] **4.1** Create TEC Integration admin page in `admin/tec-integration-page.php`
- [ ] **4.2** Build sync status dashboard:
  - Total events synced
  - Recent sync activity
  - Sync errors and warnings
  - Last sync timestamp
- [ ] **4.3** Add bulk sync tools:
  - Sync all TEC events to Outlook
  - Pull all Outlook events to TEC
  - Re-sync specific date ranges
- [ ] **4.4** Create sync history log viewer

#### Event Management Tools
- [ ] **4.5** Add sync status column to TEC events list
- [ ] **4.6** Create individual event sync actions:
  - Force sync to Outlook
  - Force sync from Outlook  
  - Break sync relationship
  - View sync history
- [ ] **4.7** Add bulk actions to TEC events list
- [ ] **4.8** Create sync status metabox on TEC event edit screen

#### Settings & Configuration
- [ ] **4.9** Build comprehensive settings panel:
  - Default organizer name
  - Default location/venue
  - Sync frequency options
  - Conflict resolution preferences
  - Field mapping customization
- [ ] **4.10** Add calendar selection (if multiple Outlook calendars)
- [ ] **4.11** Create sync exclusion rules (by category, tag, etc.)
- [ ] **4.12** Add import/export settings functionality

---

### **Phase 5: Testing, Polish & Documentation**
*Estimated: 1 week*

#### Testing Framework
- [ ] **5.1** Create comprehensive test scenarios:
  - Create event in TEC → sync to Outlook
  - Create event in Outlook → sync to TEC
  - Update events in both directions
  - Delete events in both directions
  - Recurring events handling
  - Conflict resolution scenarios
- [ ] **5.2** Test with various TEC addons (Pro, Tickets, etc.)
- [ ] **5.3** Performance testing with large numbers of events
- [ ] **5.4** Test timezone handling across different WordPress/Outlook settings

#### Error Handling & Logging
- [ ] **5.5** Implement comprehensive error handling:
  - Graph API failures
  - TEC plugin deactivation
  - Network connectivity issues
  - Permission/authentication problems
- [ ] **5.6** Add user-friendly error messages
- [ ] **5.7** Create troubleshooting guide
- [ ] **5.8** Implement automated error recovery where possible

#### Documentation & User Experience
- [ ] **5.9** Create user documentation:
  - Setup guide
  - Feature overview
  - Troubleshooting guide
  - Best practices
- [ ] **5.10** Add contextual help throughout admin interface
- [ ] **5.11** Create video tutorials/screenshots
- [ ] **5.12** Polish admin interface styling and UX

---

## 🔧 Technical Dependencies

### Required WordPress Plugins
- **The Events Calendar** (free version minimum)
- **Azure Plugin** (our plugin) with Calendar module enabled

### Required Azure Permissions
- **Calendars.ReadWrite** - Create, read, update and delete events
- **Calendars.ReadWrite.Shared** - Access shared calendars (if needed)

### WordPress Requirements
- **WordPress 5.0+** (for REST API and modern hooks)
- **PHP 7.4+** (for modern PHP features)
- **MySQL 5.6+** (for JSON data type support in metadata)

---

## ⚠️ Important Considerations

### Data Integrity
- Always backup before major sync operations
- Implement rollback mechanisms for failed syncs
- Validate data before sending to either system

### Performance
- Implement efficient querying to avoid N+1 problems
- Use WordPress transients for caching API responses  
- Batch operations where possible
- Monitor API rate limits

### User Experience
- Provide clear feedback during sync operations
- Handle long-running syncs gracefully (background processing)
- Offer granular control over what gets synced
- Make conflict resolution user-friendly

---

## 🚀 Implementation Priority

**High Priority**: Phases 1-2 (Foundation + TEC→Outlook sync)
**Medium Priority**: Phase 3 (Bidirectional sync)  
**Lower Priority**: Phases 4-5 (Advanced features + polish)

Start with Phase 1 tasks 1.1-1.5 to establish the foundation, then move systematically through each phase.

---

*Last Updated: September 18, 2025*
*Status: Planning Phase - Ready to begin implementation*
