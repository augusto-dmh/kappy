"use strict";

const { faker } = require("@faker-js/faker");

/** @type {import('sequelize-cli').Migration} */
module.exports = {
  async up(queryInterface, Sequelize) {
    const lessons = Array.from({ length: 10 }, (_, i) => ({
      id: i + 1,
      name: `Lesson ${i + 1}`,
      previous_lesson: i > 1 ? i - 1 : null,
      created_at: new Date(),
      updated_at: new Date(),
    }));

    await queryInterface.bulkInsert("lessons", lessons, {});
  },

  async down(queryInterface, Sequelize) {
    await queryInterface.bulkDelete("lessons", null, {});
  },
};
